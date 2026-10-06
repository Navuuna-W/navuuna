# Checks every Laravel migration for two hard rules (CLAUDE.md §4) that no PHP linter covers.
# Run by the `migration-lint` job in .github/workflows/ci.yml on every PR:
#   1. every migration has a real down() — not empty, not only comments;
#   2. every Schema::create names a schema-qualified table, e.g. 'core.entities'.
"""Lint Laravel migrations for an empty down() and unqualified Schema::create calls."""

from __future__ import annotations

import re
import sys
from pathlib import Path

DEFAULT_MIGRATIONS_DIRECTORY = Path("apps/api/database/migrations")

DOWN_FUNCTION_PATTERN = re.compile(r"function\s+down\s*\([^)]*\)[^{]*\{")
SCHEMA_CREATE_PATTERN = re.compile(r"Schema::create\(\s*['\"]([^'\"]+)['\"]")
COMMENT_PATTERN = re.compile(r"//[^\n]*|#[^\n]*|/\*.*?\*/", re.DOTALL)


def find_down_body(source: str) -> str | None:
    """Return the code between the braces of down(), or None if the file has no down().

    Walks the braces after `function down(...)` until they balance, so nested blocks
    inside down() are included.
    """
    match = DOWN_FUNCTION_PATTERN.search(source)
    if match is None:
        return None

    body_start = match.end()
    open_braces = 1
    for position in range(body_start, len(source)):
        if source[position] == "{":
            open_braces += 1
        if source[position] == "}":
            open_braces -= 1
        if open_braces == 0:
            return source[body_start:position]
    return None


def is_body_empty(body: str) -> bool:
    """True when the body holds nothing but whitespace and comments."""
    code_without_comments = COMMENT_PATTERN.sub("", body)
    return code_without_comments.strip() == ""


def find_problems_in_migration(source: str) -> list[str]:
    """Return one message per broken rule in a single migration's source. Empty means clean.

    Implements CLAUDE.md §4: every migration has a real down(); every table is
    schema-qualified (Bible §14.12, ADR-004a).
    """
    problems: list[str] = []

    down_body = find_down_body(source)
    if down_body is None:
        problems.append("has no down() method")
    elif is_body_empty(down_body):
        problems.append("has an empty down() method")

    for table_name in SCHEMA_CREATE_PATTERN.findall(source):
        if "." not in table_name:
            problems.append(
                f"creates unqualified table '{table_name}' (use 'schema.{table_name}')"
            )

    return problems


def lint_migrations_directory(migrations_directory: Path) -> list[str]:
    """Lint every .php file in the directory and return 'file: problem' lines.

    A missing directory is not an error: there are no migrations to break the rules yet.
    """
    if not migrations_directory.is_dir():
        return []

    report_lines: list[str] = []
    for migration_path in sorted(migrations_directory.glob("*.php")):
        source = migration_path.read_text(encoding="utf-8")
        for problem in find_problems_in_migration(source):
            report_lines.append(f"{migration_path}: {problem}")
    return report_lines


def main(arguments: list[str]) -> int:
    """Print every problem found and return the process exit code (1 if any)."""
    migrations_directory = (
        Path(arguments[0]) if arguments else DEFAULT_MIGRATIONS_DIRECTORY
    )
    report_lines = lint_migrations_directory(migrations_directory)

    for line in report_lines:
        print(line)
    if report_lines:
        return 1

    print(f"Migration lint passed ({migrations_directory}).")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
