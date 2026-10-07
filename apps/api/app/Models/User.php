<?php

// A person or machine with an account: admin, analyst, viewer or api_client (FR-20).
// Framework table in `public` (ADR-004a §1); only Laravel writes it.

declare(strict_types=1);

namespace App\Models;

use App\Auth\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A user with exactly one Role (app/Auth/Role.php). IDs are UUIDv7: HasUuids makes them in PHP
 * so Laravel knows the ID before the insert, and public.uuid_generate_v7() is the database default
 * for any other writer.
 *
 * @property Role $role
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'public.users';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }
}
