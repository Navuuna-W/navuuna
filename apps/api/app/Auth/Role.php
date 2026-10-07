<?php

// The four user roles from FR-20. Stored in public.users.role; policies and the role
// middleware read it to decide who may see or do what.

declare(strict_types=1);

namespace App\Auth;

/**
 * What a user is allowed to do. Implements Bible FR-20 (admin, analyst, viewer, api_client).
 */
enum Role: string
{
    /** Runs the platform: everything an analyst can do, plus managing accounts. */
    case Admin = 'admin';

    /** Reviews findings, including held ones that nobody else may see. */
    case Analyst = 'analyst';

    /** Reads the map and published findings only. */
    case Viewer = 'viewer';

    /** A machine that calls the JSON API with an X-Api-Key; never signs in with a session. */
    case ApiClient = 'api_client';

    /**
     * True for the roles allowed to see findings that are not public yet (held, contested, ...).
     *
     * Implements CLAUDE.md §4: no held finding in front of a non-analyst, in any form.
     */
    public function canSeeUnpublishedFindings(): bool
    {
        return $this === self::Admin || $this === self::Analyst;
    }

    /**
     * The stored values, for validation rules such as Rule::in().
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (Role $role) => $role->value, self::cases());
    }
}
