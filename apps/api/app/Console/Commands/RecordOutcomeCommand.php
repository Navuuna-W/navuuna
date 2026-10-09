<?php

// `php artisan outcomes:record {flag} {outcome} --evidence="…" --by=<email>` — records what
// turned out to be true about a published finding, weeks after it was published. The rows are
// how we later check whether findings were right (FR-19). The screen for this comes in v1.1.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Findings\Outcome;
use App\Models\AuditEvent;
use App\Models\Flag;
use App\Models\FlagOutcome;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Saves one finding outcome and its audit row together. Implements FR-19 and work pack K-17.
 * Only public findings (published, resolved) get an outcome: an outcome is what happened after
 * publishing, and held findings never leave the analysts (CLAUDE.md §4).
 */
class RecordOutcomeCommand extends Command
{
    protected $signature = 'outcomes:record
        {flag : Id of the published or resolved finding}
        {outcome : confirmed, refuted, partial or unknown}
        {--evidence= : What the outcome rests on, e.g. "site visit 12 Oct, pump replaced"}
        {--by= : Email of the analyst or admin recording it}';

    protected $description = 'Record what turned out to be true about a published finding';

    /**
     * Check every input, then save the outcome and its audit row. Non-zero exit on bad input.
     */
    public function handle(ConnectionInterface $database): int
    {
        $outcome = $this->readOutcome();
        $evidence = $this->readEvidence();
        $finding = $this->findPublicFinding();
        $recorder = $this->findRecorder();
        if ($outcome === null || $evidence === null || $finding === null || $recorder === null) {
            return self::FAILURE;
        }

        // Both rows or neither: an outcome nobody can trace is worth nothing (FR-20).
        $database->transaction(function () use ($finding, $outcome, $evidence, $recorder) {
            $savedOutcome = $this->saveOutcome($finding, $outcome, $evidence, $recorder);
            $this->recordAuditEvent($savedOutcome, $recorder);
        });

        $this->info("Outcome {$outcome->value} recorded for finding {$finding->id}.");

        return self::SUCCESS;
    }

    /**
     * The outcome argument as an Outcome, or null (with an error printed) if it isn't one.
     */
    private function readOutcome(): ?Outcome
    {
        $outcome = Outcome::tryFrom((string) $this->argument('outcome'));
        if ($outcome === null) {
            $this->error('Outcome must be one of: '.implode(', ', Outcome::values()).'.');
        }

        return $outcome;
    }

    /**
     * The --evidence text, or null (with an error printed) if it is missing or blank.
     */
    private function readEvidence(): ?string
    {
        $evidence = trim((string) $this->option('evidence'));
        if ($evidence === '') {
            $this->error('Give --evidence: what the outcome rests on.');

            return null;
        }

        return $evidence;
    }

    /**
     * The finding, or null (with an error printed) if it doesn't exist or isn't public.
     */
    private function findPublicFinding(): ?Flag
    {
        $flagId = (string) $this->argument('flag');
        // Postgres rejects a malformed uuid with an exception, so check the shape first.
        $finding = Str::isUuid($flagId) ? Flag::find($flagId) : null;
        if ($finding === null) {
            $this->error('No finding with that id.');

            return null;
        }

        if (! $finding->state->isPublic()) {
            $this->error("Only published or resolved findings get an outcome; this one is {$finding->state->value}.");

            return null;
        }

        return $finding;
    }

    /**
     * The person in --by, or null (with an error printed) if they aren't an analyst or admin.
     */
    private function findRecorder(): ?User
    {
        $recorder = User::where('email', (string) $this->option('by'))->first();
        if ($recorder === null) {
            $this->error('Give --by: the email of the analyst or admin recording the outcome.');

            return null;
        }

        // The same people who review findings judge their outcomes (FlagWorkflow::moveByPerson).
        if (! $recorder->role->canSeeUnpublishedFindings()) {
            $this->error('Only analysts and admins record outcomes; this user is '.$recorder->role->value.'.');

            return null;
        }

        return $recorder;
    }

    /**
     * Append the outcome row (flags.outcomes). Earlier outcomes stay; the newest is current.
     */
    private function saveOutcome(Flag $finding, Outcome $outcome, string $evidence, User $recorder): FlagOutcome
    {
        $flagOutcome = new FlagOutcome;
        $flagOutcome->flag_id = $finding->id;
        $flagOutcome->outcome = $outcome;
        $flagOutcome->evidence = $evidence;
        $flagOutcome->recorded_by = $recorder->id;
        $flagOutcome->save();

        return $flagOutcome;
    }

    /**
     * Append the audit row (audit.events, FR-20), the same shape FlagWorkflow writes.
     */
    private function recordAuditEvent(FlagOutcome $flagOutcome, User $recorder): void
    {
        $auditEvent = new AuditEvent;
        $auditEvent->user_id = $recorder->id;
        $auditEvent->action = 'outcome_recorded';
        $auditEvent->target_table = 'flags.outcomes';
        $auditEvent->target_id = $flagOutcome->id;
        $auditEvent->after = [
            'flag_id' => $flagOutcome->flag_id,
            'outcome' => $flagOutcome->outcome->value,
            'evidence' => $flagOutcome->evidence,
        ];
        $auditEvent->save();
    }
}
