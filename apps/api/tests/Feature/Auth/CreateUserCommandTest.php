<?php

// Checks `php artisan users:create`: it stores the role and a hashed password, and refuses
// bad input instead of creating a half-valid account.
// Written as a PHPUnit class, not Pest closures: answering the hidden password prompt needs
// $this->artisan(), which PHPStan can only type inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'correct horse battery';

    public function test_it_creates_a_user_with_the_given_role_and_a_hashed_password(): void
    {
        $command = $this->createUser(['--role' => 'analyst'], self::VALID_PASSWORD);

        $command->assertSuccessful()->run();

        $user = User::where('email', 'someone@navuuna.test')->sole();
        $this->assertSame(Role::Analyst, $user->role);
        $this->assertNotSame(self::VALID_PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->password));
    }

    /**
     * @param  array<string, string|null>  $badInput
     */
    #[DataProvider('badInputs')]
    public function test_it_refuses_bad_input_and_creates_nobody(array $badInput, string $password): void
    {
        $command = $this->createUser($badInput, $password);

        $command->assertFailed()->run();

        $this->assertSame(0, User::count());
    }

    /**
     * @return array<string, array{array<string, string|null>, string}>
     */
    public static function badInputs(): array
    {
        return [
            'unknown role' => [['--role' => 'superuser'], self::VALID_PASSWORD],
            'missing role' => [['--role' => null], self::VALID_PASSWORD],
            'missing name' => [['--name' => null], self::VALID_PASSWORD],
            'bad email' => [['email' => 'not-an-email'], self::VALID_PASSWORD],
            'short password' => [[], 'short'],
        ];
    }

    public function test_it_refuses_an_email_that_already_has_an_account(): void
    {
        User::factory()->create(['email' => 'someone@navuuna.test']);

        $command = $this->createUser([], self::VALID_PASSWORD);

        $command->expectsOutputToContain('already been taken')->assertFailed()->run();
        $this->assertSame(1, User::count());
    }

    /**
     * Start `users:create` for a valid viewer, with $overrides replacing any argument, and
     * answer the password prompt with $password.
     *
     * @param  array<string, string|null>  $overrides
     */
    private function createUser(array $overrides, string $password): PendingCommand
    {
        $arguments = array_merge([
            'email' => 'someone@navuuna.test',
            '--name' => 'Someone',
            '--role' => 'viewer',
        ], $overrides);

        $command = $this->artisan('users:create', $arguments);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command->expectsQuestion('Password', $password);
    }
}
