// Dev helper. Prints which fixture entity IDs own findings and which state they are in.
// Used to pick stable IDs for Playwright smoke tests.
//
// Run with:  node --experimental-strip-types scripts/list-fixture-findings.mjs
//
// It uses tsx-style resolution via a vite build-time step; keeping it short so we can
// delete it later without regret.
import('../fixtures/index.ts').then(({ fixtures }) => {
  const bundle = fixtures();
  const withFindings = bundle.entities.filter((e) => e.detail.findings.length > 0);
  for (const e of withFindings) {
    console.log(
      e.detail.id,
      '-',
      e.detail.findings.map((f) => `${f.state}/${f.severity}/${f.sub_variable}`).join(', ')
    );
  }
});
