<?php

// One narrative template from a module's findings.yml, with the value path that fills each of its
// {placeholders}. FindingsFile reads it; the narrative filler turns it into the finding's text.

declare(strict_types=1);

namespace App\Findings;

/**
 * A narrative with {placeholders} and where each placeholder's value comes from.
 * Implements docs/modules/findings.md (D-20).
 */
final readonly class FindingTemplate
{
    /** A placeholder is a lower-case name in braces, e.g. {declared_date}. */
    public const PLACEHOLDER_PATTERN = '/\{([a-z_]+)\}/';

    /**
     * @param  array<string, string>  $fields  placeholder name → value path, e.g. record.record_date|date
     */
    public function __construct(
        public string $narrative,
        public array $fields,
    ) {}

    /**
     * The placeholder names in the narrative, in order of first use.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $this->narrative, $matches);

        return array_values(array_unique($matches[1]));
    }
}
