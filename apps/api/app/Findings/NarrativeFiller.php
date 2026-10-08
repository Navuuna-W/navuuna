<?php

// Fills a narrative template from a module's findings.yml with one finding's values. Pure: the
// findings engine passes the rows in. If any placeholder has no value the result is null and the
// finding is not raised — a claim about a named party never has a blank in it.

declare(strict_types=1);

namespace App\Findings;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Implements docs/modules/findings.md: rule 2 (both sources, both dates), rule 3 (whole units,
 * percentages without decimals) and rule 4 (no value → not raised).
 */
final class NarrativeFiller
{
    /** Every date on screen is local to Nairobi (PRD §10). */
    private const TIME_ZONE = 'Africa/Nairobi';

    /** "14 Sep 2026": day without a leading zero, three-letter month, year (PRD §10). */
    private const DATE_FORMAT = 'j M Y';

    private const PERCENT_PER_FRACTION = 100;

    /** subject (e.g. record, score[2.5]) · column path (dots walk into jsonb) · optional filter. */
    private const VALUE_PATH_PARTS_PATTERN = '/^(score\[[^\]]+\]|[a-z_]+)\.([a-z0-9_.]+)(?:\|([a-z]+))?$/';

    /**
     * The narrative with every placeholder filled, or null when any placeholder has no value.
     */
    public static function fill(FindingTemplate $template, NarrativeValues $values): ?FilledNarrative
    {
        $filledValues = [];
        foreach ($template->placeholders() as $placeholder) {
            $value = self::valueOf($template->fields[$placeholder], $values);
            if ($value === null) {
                return null;
            }
            $filledValues[$placeholder] = $value;
        }

        $text = preg_replace_callback(
            FindingTemplate::PLACEHOLDER_PATTERN,
            fn (array $match) => $filledValues[$match[1]],
            $template->narrative,
        );

        return new FilledNarrative(
            text: (string) $text,
            declared: self::withPrefix('declared_', $filledValues),
            observed: self::withPrefix('observed_', $filledValues),
        );
    }

    /**
     * The text of one value path, e.g. "record.record_date|date" → "1 Mar 2024". Null when the
     * row, the column or the value is missing or empty, or not a plain number or text.
     */
    private static function valueOf(string $valuePath, NarrativeValues $values): ?string
    {
        // The subject is everything before the first dot outside brackets: score[2.5].value
        // splits into "score[2.5]" and "value". FindingsFile has already checked the shape.
        preg_match(self::VALUE_PATH_PARTS_PATTERN, $valuePath, $parts);
        [, $subject, $columnPath] = $parts;
        $filter = ($parts[3] ?? '') === '' ? null : $parts[3];

        $value = self::walk($values->rowFor($subject), explode('.', $columnPath));
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $text = self::applyFilter($value, $filter);

        // Empty text is a blank in the narrative too (findings.md rule 4).
        return $text === null || trim($text) === '' ? null : $text;
    }

    /**
     * Follow column names into a row, then into its jsonb. Null when any step is missing.
     *
     * @param  array<string, mixed>|null  $row
     * @param  list<string>  $columnNames
     */
    private static function walk(?array $row, array $columnNames): mixed
    {
        $value = $row;
        foreach ($columnNames as $columnName) {
            if (! is_array($value) || ! array_key_exists($columnName, $value)) {
                return null;
            }
            $value = $value[$columnName];
        }

        return $value;
    }

    /**
     * Format a value for the narrative: as is, as a Nairobi date, or as a whole number
     * (a fraction × 100 for |percent). Null when a number filter gets something that is not one.
     */
    private static function applyFilter(string|int|float $value, ?string $filter): ?string
    {
        if ($filter === null) {
            return (string) $value;
        }
        if ($filter === 'date') {
            return self::formatDate((string) $value);
        }
        if (! is_numeric($value)) {
            return null;
        }
        $number = $filter === 'percent' ? (float) $value * self::PERCENT_PER_FRACTION : (float) $value;

        return (string) (int) round($number);
    }

    /**
     * "2026-09-13T22:30:00Z" → "14 Sep 2026" in Nairobi. Null for text that is not a date, so
     * a bad date leaves the finding unraised instead of printing nonsense.
     */
    private static function formatDate(string $value): ?string
    {
        try {
            return CarbonImmutable::parse($value)->setTimezone(self::TIME_ZONE)->format(self::DATE_FORMAT);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * The values whose placeholder starts with the prefix, keyed without it.
     *
     * @param  array<string, string>  $filledValues
     * @return array<string, string>
     */
    private static function withPrefix(string $prefix, array $filledValues): array
    {
        $prefixed = [];
        foreach ($filledValues as $placeholder => $value) {
            if (str_starts_with($placeholder, $prefix)) {
                $prefixed[substr($placeholder, strlen($prefix))] = $value;
            }
        }

        return $prefixed;
    }
}
