# docs/modules/

One spec file per module (`water.md`, `roads.md`, `land.md`, …). A spec is written and merged
**before** any adapter code for that module (D-12): it says, per sub-variable, what the signal
is, which inputs it needs, the formula, what score 0 and 100 mean, the confidence rule, and
every `null_not_measured` reason string. Words come from `docs/CONTEXT.md`.
Owner: Devyan (Signal lane). Adapter code lives in `services/signals/modules/<module>/`.
