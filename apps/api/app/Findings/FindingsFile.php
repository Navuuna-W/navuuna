<?php

// A module's findings.yml (Devyan's, D-20): the narrative template per 2.x sub-variable and the
// value path behind each placeholder. The findings engine reads it to write a finding's text and
// its declared / observed values, so the engine itself never names a water or road column.

declare(strict_types=1);

namespace App\Findings;

use Symfony\Component\Yaml\Yaml;

/**
 * The narrative templates of one module. Implements docs/modules/findings.md and work pack K-13
 * ("narrative from Devyan's templates"; "the engine stays module-agnostic").
 */
final readonly class FindingsFile
{
    /**
     * A value path: what it reads, a column (dots walk into jsonb), and an optional filter.
     * See the comment at the top of a findings.yml for the meaning of each part.
     */
    public const VALUE_PATH_PATTERN =
        '/^(entity|record|observation|eo_stat|score|score\[[0-9]\.[0-9]\])\.[a-z0-9_]+(\.[a-z0-9_]+)*(\|(date|percent|whole))?$/';

    /**
     * @param  array<string, FindingTemplate>  $templatesBySubId
     */
    private function __construct(
        private array $templatesBySubId,
        public ?FindingTemplate $closingLine,
    ) {}

    /**
     * Read and check a findings.yml.
     *
     * @throws InvalidFindingsFile when the file is missing, has no sub-variables, or a template
     *                             has a placeholder without a valid value path
     */
    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidFindingsFile("Findings file not found: {$path}");
        }

        $content = Yaml::parseFile($path);
        $subVariables = is_array($content) ? ($content['sub_variables'] ?? null) : null;
        if (! is_array($subVariables) || $subVariables === []) {
            throw new InvalidFindingsFile("Findings file has no sub_variables: {$path}");
        }

        $templatesBySubId = [];
        foreach ($subVariables as $subId => $template) {
            $templatesBySubId[(string) $subId] = self::readTemplate("sub-variable {$subId}", $template);
        }
        $closingLine = isset($content['closing_line'])
            ? self::readTemplate('closing_line', $content['closing_line'])
            : null;

        return new self($templatesBySubId, $closingLine);
    }

    /**
     * The narrative template of a sub-variable, or null when the module has none for it.
     */
    public function templateFor(string $subId): ?FindingTemplate
    {
        return $this->templatesBySubId[$subId] ?? null;
    }

    /**
     * Check one template: a narrative, and a valid value path for every placeholder in it.
     * Catching a typo here stops a finding from ever being raised with a blank in its text
     * (findings.md rule 4).
     */
    private static function readTemplate(string $label, mixed $template): FindingTemplate
    {
        $narrative = is_array($template) ? ($template['narrative'] ?? null) : null;
        $fields = is_array($template) ? ($template['fields'] ?? []) : [];
        if (! is_string($narrative) || trim($narrative) === '' || ! is_array($fields)) {
            throw new InvalidFindingsFile("{$label}: needs a narrative and a map of fields.");
        }

        $template = new FindingTemplate($narrative, array_map(strval(...), $fields));
        foreach ($template->placeholders() as $placeholder) {
            $valuePath = $template->fields[$placeholder] ?? '';
            if (preg_match(self::VALUE_PATH_PATTERN, $valuePath) !== 1) {
                throw new InvalidFindingsFile("{$label}: {{$placeholder}} has no valid value path.");
            }
        }

        return $template;
    }
}
