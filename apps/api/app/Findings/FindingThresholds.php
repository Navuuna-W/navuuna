<?php

// The numbers behind "when is a gap big enough to become a finding, and how serious is it".
// RaiseRules reads them when it raises a finding.
// Changing one changes what gets raised, so it needs a new ADR (ADR-010 Consequences).

declare(strict_types=1);

namespace App\Findings;

/**
 * Implements ADR-010 Decision 1 (DEC-09). Scores are the direction-neutral 0–100
 * sub-variable scores, where higher = bigger gap.
 */
final class FindingThresholds
{
    /** DEC-09 rule 3: a less certain score never becomes a claim about a named party. */
    public const MIN_FINDING_CONFIDENCE = 0.5;

    /**
     * DEC-09 rule 4: the score at which each sub-variable raises a finding. A sub-variable not
     * listed never raises one: 2.5 Record staleness never raises alone (an old record is not a
     * claim about anyone), and 2.3 Attribute gap has no threshold yet (roads, after 7 Oct).
     */
    public const RAISE_AT_SCORE = [
        '2.1' => 100.0,
        '2.2' => 30.0,
        '2.4' => 50.0,
    ];

    /** 2.2 Magnitude gap: scores at or above this are medium. */
    private const MAGNITUDE_GAP_MEDIUM_FROM = 50.0;

    /** 2.2 Magnitude gap: scores at or above this are high. */
    private const MAGNITUDE_GAP_HIGH_FROM = 75.0;

    /** 2.4 Status gap: 100 = declared working, observed not working; 50 = intermittent. */
    private const STATUS_GAP_HIGH_FROM = 100.0;

    /**
     * The score at which a sub-variable raises a finding, or null when it never raises one.
     */
    public static function raiseAtScore(string $subId): ?float
    {
        return self::RAISE_AT_SCORE[$subId] ?? null;
    }

    /**
     * The severity of a finding raised for this sub-variable at this score. Only called for
     * sub-variables with a raise threshold. Implements the severity columns of DEC-09.
     */
    public static function severityFor(string $subId, float $score): Severity
    {
        return match ($subId) {
            '2.2' => self::magnitudeGapSeverity($score),
            '2.4' => $score >= self::STATUS_GAP_HIGH_FROM ? Severity::High : Severity::Low,
            // 2.1 Existence gap: "the record says it exists and it does not" is always high.
            default => Severity::High,
        };
    }

    /**
     * 2.2 Magnitude gap: 30–49 low · 50–74 medium · 75–100 high.
     */
    private static function magnitudeGapSeverity(float $score): Severity
    {
        if ($score >= self::MAGNITUDE_GAP_HIGH_FROM) {
            return Severity::High;
        }
        if ($score >= self::MAGNITUDE_GAP_MEDIUM_FROM) {
            return Severity::Medium;
        }

        return Severity::Low;
    }
}
