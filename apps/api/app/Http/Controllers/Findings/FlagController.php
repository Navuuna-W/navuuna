<?php

// GET /api/v1/flags?state= (the analysts' review queue) and GET /api/v1/flags/{flag} (one finding
// with its evidence pack). FlagPolicy decides who sees what; this file only shapes the JSON.

declare(strict_types=1);

namespace App\Http\Controllers\Findings;

use App\Findings\FlagState;
use App\Models\EvidencePack;
use App\Models\Flag;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reads findings. Implements Bible §11.1 (GET /flags/{id}: held only for analysts and admins)
 * and work pack K-14 (GET /flags?state=, analysts and admins, newest first).
 */
class FlagController
{
    /** Findings per page of the review queue. */
    private const QUEUE_PAGE_SIZE = 50;

    /**
     * The review queue: newest findings first, optionally in one state. 403 for non-reviewers.
     */
    public function index(Request $request, Gate $gate): JsonResponse
    {
        $gate->authorize('viewAny', Flag::class);
        $input = $request->validate(['state' => ['nullable', Rule::enum(FlagState::class)]]);

        $query = Flag::query()->orderByDesc('detected_at')->orderByDesc('id');
        if (isset($input['state'])) {
            $query->where('state', $input['state']);
        }

        $page = $query->simplePaginate(self::QUEUE_PAGE_SIZE)->through(fn (Flag $flag) => $this->summarise($flag));

        return new JsonResponse($page);
    }

    /**
     * One finding with its evidence pack. 404 when the user may not see it (FlagPolicy::view).
     */
    public function show(Flag $flag, Gate $gate): JsonResponse
    {
        $gate->authorize('view', $flag);

        $evidencePack = $flag->evidencePack;

        return new JsonResponse([
            ...$this->summarise($flag),
            'evidence_pack' => $evidencePack === null ? null : $this->describeEvidence($evidencePack),
        ]);
    }

    /**
     * The fields every finding shows: what was declared, what was observed, and the gap.
     *
     * @return array<string, mixed>
     */
    private function summarise(Flag $flag): array
    {
        return [
            'id' => $flag->id,
            'entity_id' => $flag->entity_id,
            'sub_id' => $flag->sub_id,
            'severity' => $flag->severity,
            'state' => $flag->state->value,
            'declared' => $flag->declared,
            'observed' => $flag->observed,
            'gap' => $flag->gap,
            'detected_at' => $flag->detected_at->toIso8601String(),
            'state_changed_at' => $flag->state_changed_at->toIso8601String(),
        ];
    }

    /**
     * The evidence a finding rests on (Bible §6.5: a flag always comes with its evidence pack).
     *
     * @return array<string, mixed>
     */
    private function describeEvidence(EvidencePack $evidencePack): array
    {
        return [
            'narrative' => $evidencePack->narrative,
            'record_ids' => $evidencePack->record_ids,
            'observation_ids' => $evidencePack->observation_ids,
            'document_ids' => $evidencePack->document_ids,
            'eo_stat_ids' => $evidencePack->eo_stat_ids,
            'adapter_version' => $evidencePack->adapter_version,
            'generated_at' => $evidencePack->generated_at->toIso8601String(),
        ];
    }
}
