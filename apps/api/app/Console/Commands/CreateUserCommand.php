<?php

// `php artisan users:create` — the only way an account is made. There is no sign-up page:
// accounts are created by us (PRD 6.2). Writes one row to public.users with its role (FR-20).

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Creates one user with a name, email, role and password. The password is asked for with a
 * hidden prompt so it never lands in shell history. Implements work pack K-11 (`users:create`).
 */
class CreateUserCommand extends Command
{
    /** Long enough to resist guessing; NFR-05 asks for an OWASP top 10 pass. */
    private const MINIMUM_PASSWORD_LENGTH = 12;

    protected $signature = 'users:create
        {email : The address the user signs in with}
        {--name= : The name shown in the audit log}
        {--role= : One of admin, analyst, viewer, api_client}';

    protected $description = 'Create a user account with one role';

    /**
     * Validate the input, then create the user. Returns a non-zero exit code on bad input.
     */
    public function handle(ValidatorFactory $validatorFactory): int
    {
        $input = [
            'email' => $this->argument('email'),
            'name' => $this->option('name'),
            'role' => $this->option('role'),
            'password' => $this->secret('Password'),
        ];

        $validator = $validatorFactory->make($input, $this->rules());
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create($validator->validated());
        $this->info("Created {$input['role']} {$input['email']}.");

        return self::SUCCESS;
    }

    /**
     * The validation rules for a new account.
     *
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::unique(User::class, 'email')],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(Role::values())],
            'password' => ['required', 'string', 'min:'.self::MINIMUM_PASSWORD_LENGTH],
        ];
    }
}
