<?php

// Thrown when a module's findings.yml cannot be used. Narratives are claims about a named party
// (Bible §6.5), so a broken file is a bug to fix, never a reason to raise a finding with a gap
// in its text.

declare(strict_types=1);

namespace App\Findings;

use RuntimeException;

/**
 * findings.yml is missing, unreadable, or breaks a rule of docs/modules/findings.md.
 */
final class InvalidFindingsFile extends RuntimeException {}
