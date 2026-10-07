<?php

// Who may see and move findings. Controllers call $this->authorize('view', $flag) or
// Gate::allows('transition', $flag); Laravel finds this class by name for App\Models\Flag.

declare(strict_types=1);

namespace App\Policies;

use App\Models\Flag;
use App\Models\User;

/**
 * Keeps unpublished findings away from everyone but analysts and admins.
 * Implements CLAUDE.md §4 (no held finding in front of a non-analyst) and Bible §11.
 */
class FlagPolicy
{
    /**
     * May the user open the review queue (GET /flags?state=, K-14)? Analysts and admins only,
     * because the queue is mostly held findings.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canSeeUnpublishedFindings();
    }

    /**
     * May the user see this one finding? Published and resolved findings are public;
     * every other state is for analysts and admins only.
     */
    public function view(User $user, Flag $flag): bool
    {
        if (in_array($flag->state, Flag::PUBLIC_STATES, true)) {
            return true;
        }

        return $user->role->canSeeUnpublishedFindings();
    }

    /**
     * May the user move this finding to another state (FR-11)? Analysts and admins only.
     * Which moves are legal is FlagWorkflow's job (K-14), not this policy's.
     */
    public function transition(User $user, Flag $flag): bool
    {
        return $user->role->canSeeUnpublishedFindings();
    }
}
