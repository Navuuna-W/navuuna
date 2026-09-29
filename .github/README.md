# .github

Repository governance: CI workflows, the PR and issue templates, CODEOWNERS and the label script.
We are on the GitHub Free plan, so GitHub does not enforce branch protection. These files and
the local hooks do that job instead (see `docs/adr/001-monorepo.md`).

| File | What it does |
|---|---|
| `workflows/ci.yml` | Checks run on every PR to `main` |
| `pull_request_template.md` | The Bible §14.4 sections and checklist, filled on every PR |
| `ISSUE_TEMPLATE/` | Task and bug forms; blank issues are off |
| `CODEOWNERS` | The required reviewer for each path (work pack A2) |
| `labels.sh` | Creates the `module:*`, `layer:*`, `pri:*`, `owner:*` labels |

## Set up the local hooks (once per clone)

```sh
pip install pre-commit        # or: brew install pre-commit
git config --unset core.hooksPath   # only if you set it earlier; pre-commit needs the default
pre-commit install
```

This blocks commits and pushes to `main`, checks commit messages and runs the linters.

## Test

```sh
pre-commit run --all-files
bash .github/labels.sh        # re-create or update the labels (safe to repeat)
```
