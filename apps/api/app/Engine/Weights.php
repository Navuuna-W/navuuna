<?php

// The sub-variable weights the rollup uses, read from services/signals/engine/weights.yml
// (Devyan's file, Bible §6.7). Loaded once per rollup run; every variable score stores the
// weight_version that produced it (NFR-08).

declare(strict_types=1);

namespace App\Engine;

use Symfony\Component\Yaml\Yaml;

/**
 * The weights of every weighted sub-variable, grouped by variable (1–5). Gates and the guard
 * have no weight, so they never appear here (Bible §6.3).
 */
final readonly class Weights
{
    /** V1 Activity · V2 Discrepancy · V3 Momentum · V4 Resource security · V5 Accessibility. */
    public const VARIABLE_IDS = [1, 2, 3, 4, 5];

    /** Weights may be written to two decimals; their sum can be off by float rounding only. */
    private const SUM_TOLERANCE = 0.0001;

    /** Each top-level variable key in weights.yml starts with V1_ … V5_, e.g. V1_activity. */
    private const VARIABLE_KEY_PATTERN = '/^V([1-5])_/';

    /**
     * @param  array<int, array<string, float>>  $weightsByVariable  variable id → sub_id → weight
     */
    private function __construct(
        public int $weightVersion,
        private array $weightsByVariable,
    ) {}

    /**
     * Read and check weights.yml.
     *
     * @throws InvalidWeightsFile when the file is missing a version, a variable, or has weights
     *                            that do not sum to 1.00
     */
    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidWeightsFile("Weights file not found: {$path}");
        }

        $content = Yaml::parseFile($path);
        if (! is_array($content)) {
            throw new InvalidWeightsFile("Weights file is not a YAML map: {$path}");
        }

        $weightVersion = $content['weight_version'] ?? null;
        if (! is_int($weightVersion) || $weightVersion < 1) {
            throw new InvalidWeightsFile('weight_version must be a whole number of at least 1.');
        }

        return new self($weightVersion, self::readVariables($content));
    }

    /**
     * The weights of one variable's contributors.
     *
     * @return array<string, float> sub_id → weight
     */
    public function forVariable(int $variableId): array
    {
        return $this->weightsByVariable[$variableId];
    }

    /**
     * @param  array<mixed>  $content
     * @return array<int, array<string, float>>
     */
    private static function readVariables(array $content): array
    {
        $weightsByVariable = [];
        foreach ($content as $key => $weights) {
            if (! preg_match(self::VARIABLE_KEY_PATTERN, (string) $key, $match)) {
                continue;
            }
            $weightsByVariable[(int) $match[1]] = self::readWeights((string) $key, $weights);
        }

        foreach (self::VARIABLE_IDS as $variableId) {
            if (! isset($weightsByVariable[$variableId])) {
                throw new InvalidWeightsFile("Weights file has no entry for variable V{$variableId}.");
            }
        }

        return $weightsByVariable;
    }

    /**
     * @return array<string, float>
     */
    private static function readWeights(string $variableKey, mixed $weights): array
    {
        if (! is_array($weights) || $weights === []) {
            throw new InvalidWeightsFile("{$variableKey} must list its sub-variable weights.");
        }

        $weightsBySubId = [];
        foreach ($weights as $subId => $weight) {
            if (! is_float($weight) && ! is_int($weight)) {
                throw new InvalidWeightsFile("{$variableKey} {$subId}: weight must be a number.");
            }
            $weightsBySubId[(string) $subId] = (float) $weight;
        }

        if (abs(array_sum($weightsBySubId) - 1.0) > self::SUM_TOLERANCE) {
            throw new InvalidWeightsFile("{$variableKey}: weights must sum to 1.00.");
        }

        return $weightsBySubId;
    }
}
