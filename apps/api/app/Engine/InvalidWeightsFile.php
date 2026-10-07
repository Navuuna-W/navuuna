<?php

// Thrown when weights.yml cannot be used. The rollup stops rather than score with wrong weights:
// a weight change needs an ADR (CLAUDE.md §4), so a broken file is a bug to fix, not to skip.

declare(strict_types=1);

namespace App\Engine;

use RuntimeException;

/**
 * weights.yml is missing, unreadable, or breaks a rule of Bible §6.7.
 */
final class InvalidWeightsFile extends RuntimeException {}
