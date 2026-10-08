<?php

// A narrative with every placeholder filled, plus the declared and observed values that went into
// it. The findings engine saves the text in flags.evidence_packs.narrative and the two maps in
// flags.flags.declared / observed.

declare(strict_types=1);

namespace App\Findings;

/**
 * The finding's text and the values behind it. Implements docs/modules/findings.md rules 2–4.
 */
final readonly class FilledNarrative
{
    /**
     * @param  array<string, string>  $declared  declared_* placeholders, without the prefix
     * @param  array<string, string>  $observed  observed_* placeholders, without the prefix
     */
    public function __construct(
        public string $text,
        public array $declared,
        public array $observed,
    ) {}
}
