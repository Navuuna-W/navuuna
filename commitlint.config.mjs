// Commit message rules from Bible §14.3, checked by the local commit-msg hook and by the
// `commitlint` CI job (which also checks the PR title, because that becomes the squash commit).
// Format: `type(scope): summary`, then a body, then a `Refs: <task ID>, <FR/ADR>` footer.

const ALLOWED_TYPES = ['feat', 'fix', 'refactor', 'perf', 'test', 'docs', 'chore', 'ci', 'data'];

// Bible §14.3 scopes, plus `adr` and `ci`, which the Bible's own examples use.
const ALLOWED_SCOPES = [
  'api', 'web', 'ingest', 'signals', 'engine', 'tiles',
  'water', 'roads', 'land', 'energy', 'safety',
  'infra', 'docs', 'adr', 'ci',
];

const MAX_HEADER_LENGTH = 72;

// Every commit on a branch must say which task it belongs to, so `git log --grep K-09` finds it
// (CLAUDE.md §6). The PR title is linted with this rule switched off: the squash commit's body
// comes from the PR, which carries its own `Refs:` line.
function hasRefsFooter(parsedCommit) {
  const is_refs_present = /^Refs: .+/m.test(parsedCommit.raw ?? '');
  return [is_refs_present, 'add a footer line "Refs: <task ID>, <FR/ADR>", e.g. "Refs: K-01, ADR-001"'];
}

export default {
  extends: ['@commitlint/config-conventional'],
  plugins: [{ rules: { 'refs-footer-present': hasRefsFooter } }],
  rules: {
    'type-enum': [2, 'always', ALLOWED_TYPES],
    'scope-enum': [2, 'always', ALLOWED_SCOPES],
    'header-max-length': [2, 'always', MAX_HEADER_LENGTH],
    'refs-footer-present': [process.env.COMMITLINT_SKIP_REFS === 'true' ? 0 : 2, 'always'],
  },
};
