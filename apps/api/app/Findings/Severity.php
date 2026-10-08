<?php

// How serious a finding is. Stored in flags.flags.severity; FindingThresholds decides it from the
// sub-variable's score when the findings engine raises the finding.

declare(strict_types=1);

namespace App\Findings;

/**
 * The three severities of ADR-010 Decision 1 (DEC-09).
 */
enum Severity: string
{
    case Low = 'low';

    case Medium = 'medium';

    case High = 'high';
}
