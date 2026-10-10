<?php

// Shapes the GET /api/v1/me body: who the signed-in person is, and whether the review screens
// exist for them. Deliberately narrow — the email address and every other PII-tagged column
// stay out (NFR-06), and nothing here hints at what is in the review queue (DEC-08, A-19).

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Identity and capability for the signed-in user. Implements DEC-13 (`GET /me`) and FR-20
 * (one role per user).
 */
class CurrentUserResource extends JsonResource
{
    /**
     * No `data` envelope, so /me reads like the rest of /api/v1 (Bible §11.1).
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Typed on purpose: the parent's `$resource` is untyped, so passing the wrong model
     * would only show up as a broken response at runtime.
     */
    public function __construct(private readonly User $signedInUser)
    {
        parent::__construct($signedInUser);
    }

    /**
     * The three things the frontend needs: a name to greet with (S1 header, S7 sign-in
     * confirmation), the role the server is treating the session as, and whether the review
     * screens exist for this user.
     *
     * `can_review_findings` is Role::canSeeUnpublishedFindings() — the same check
     * FlagPolicy::viewAny applies to GET /flags, so the role-to-capability rule lives on the
     * server and the client never re-derives it (A-19). It is a capability, never a count:
     * a number here would tell a viewer that held findings exist (DEC-08).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->signedInUser->name,
            'role' => $this->signedInUser->role->value,
            'can_review_findings' => $this->signedInUser->role->canSeeUnpublishedFindings(),
        ];
    }
}
