# Tests for lint_migrations.py, the checker behind the `migration-lint` CI job.
# Each test writes a small fake migration to a temp folder and runs the linter on it.
"""Tests for the migration linter."""

from __future__ import annotations

from pathlib import Path

from lint_migrations import lint_migrations_directory, main

GOOD_MIGRATION = """<?php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.entities', function (Blueprint $table) {
            $table->uuid('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core.entities');
    }
};
"""


def write_migration(directory: Path, source: str) -> Path:
    """Write one migration file into the folder and return its path."""
    migration_path = directory / "2026_10_07_000000_example.php"
    migration_path.write_text(source, encoding="utf-8")
    return migration_path


def test_qualified_schema_create_with_real_down_passes(tmp_path: Path) -> None:
    write_migration(tmp_path, GOOD_MIGRATION)

    problems = lint_migrations_directory(tmp_path)

    assert problems == []


def test_migration_with_empty_down_fails(tmp_path: Path) -> None:
    source = GOOD_MIGRATION.replace("Schema::dropIfExists('core.entities');", "")
    write_migration(tmp_path, source)

    problems = lint_migrations_directory(tmp_path)

    assert len(problems) == 1
    assert "empty down()" in problems[0]


def test_migration_with_comment_only_down_fails(tmp_path: Path) -> None:
    source = GOOD_MIGRATION.replace(
        "Schema::dropIfExists('core.entities');", "// nothing to undo\n /* really */"
    )
    write_migration(tmp_path, source)

    problems = lint_migrations_directory(tmp_path)

    assert len(problems) == 1
    assert "empty down()" in problems[0]


def test_migration_without_down_fails(tmp_path: Path) -> None:
    source = GOOD_MIGRATION.split("public function down")[0] + "};\n"
    write_migration(tmp_path, source)

    problems = lint_migrations_directory(tmp_path)

    assert len(problems) == 1
    assert "no down()" in problems[0]


def test_unqualified_schema_create_fails(tmp_path: Path) -> None:
    source = GOOD_MIGRATION.replace(
        "Schema::create('core.entities'", "Schema::create('entities'"
    )
    write_migration(tmp_path, source)

    problems = lint_migrations_directory(tmp_path)

    assert len(problems) == 1
    assert "unqualified table 'entities'" in problems[0]


def test_no_migrations_directory_passes(tmp_path: Path) -> None:
    missing_directory = tmp_path / "does_not_exist"

    exit_code = main([str(missing_directory)])

    assert exit_code == 0


def test_broken_migration_gives_exit_code_one(tmp_path: Path) -> None:
    write_migration(tmp_path, GOOD_MIGRATION.replace("'core.entities'", "'entities'"))

    exit_code = main([str(tmp_path)])

    assert exit_code == 1
