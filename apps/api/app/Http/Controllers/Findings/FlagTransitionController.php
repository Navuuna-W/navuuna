<?php

// POST /api/v1/flags/{flag}/transition — the review screen's buttons. One finding per request;
// there is deliberately no bulk endpoint (work pack K-14). ReviewFlag does the work.

declare(strict_types=1);

namespace App\Http\Controllers\Findings;

use App\Findings\IllegalFlagTransition;
use App\Findings\ReviewAction;
use App\Findings\ReviewFlag;
use App\Models\Flag;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Applies one review action to one finding. Implements Bible §11.1 (POST /flags/{id}/transition),
 * FR-11 and ADR-010 DEC-10 (body: action, note, explanation).
 */
class FlagTransitionController
{
    /**
     * Returns 200 with the finding's new state, 422 when the action doesn't fit the finding,
     * and 404 for anyone who may not review (FlagPolicy hides that the finding exists).
     */
    public function __invoke(Request $request, Flag $flag, Gate $gate, ReviewFlag $reviewFlag): JsonResponse
    {
        $gate->authorize('transition', $flag);
        $input = $request->validate([
            'action' => ['required', Rule::enum(ReviewAction::class)],
            'note' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
        ]);

        // auth:sanctum,api_key has already answered 401 to guests; this keeps the type honest.
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        try {
            $reviewFlag->apply($flag, $user, ReviewAction::from($input['action']), $input['note'], $input['explanation'] ?? null);
        } catch (IllegalFlagTransition $refusal) {
            return new JsonResponse(['message' => $refusal->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['id' => $flag->id, 'state' => $flag->state->value]);
    }
}
