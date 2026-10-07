<?php

// `php artisan keys:issue {name} --user=<email>` — makes an API key for an api_client user and
// prints it once. Only its SHA-256 is saved, so a lost key is re-issued, never recovered.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\Role;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Issues one API key. Implements work pack K-11 (`keys:issue`, shown once) and NFR-05 (hashed).
 */
class IssueApiKeyCommand extends Command
{
    /** Every key starts with this, so a leaked key is easy to recognise in logs and scanners. */
    private const KEY_PREFIX = 'nv_';

    /** 40 random letters and digits: about 238 bits, far beyond guessing. */
    private const KEY_RANDOM_LENGTH = 40;

    protected $signature = 'keys:issue
        {name : What the key is for, e.g. "county-gis-team"}
        {--user= : Email of the api_client user the key signs in as}';

    protected $description = 'Issue an API key for an api_client user and show it once';

    /**
     * Check the user, store the key's hash and print the plain key. Non-zero exit on bad input.
     */
    public function handle(): int
    {
        $user = User::where('email', (string) $this->option('user'))->first();
        if ($user === null) {
            $this->error('No user with that email. Create one with users:create --role=api_client.');

            return self::FAILURE;
        }

        if ($user->role !== Role::ApiClient) {
            $this->error('API keys are only for api_client users; this user is '.$user->role->value.'.');

            return self::FAILURE;
        }

        $plainKey = self::KEY_PREFIX.Str::random(self::KEY_RANDOM_LENGTH);
        ApiKey::create([
            'user_id' => $user->id,
            'name' => (string) $this->argument('name'),
            'key_hash' => ApiKey::hashKey($plainKey),
        ]);

        $this->info('Key issued. Copy it now: it will not be shown again.');
        $this->line($plainKey);

        return self::SUCCESS;
    }
}
