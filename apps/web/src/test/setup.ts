// Vitest + Testing Library setup. Loaded once by `vite.config.ts` -> test.setupFiles.
// Keeps every test file free of boilerplate.

import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

afterEach(() => {
  cleanup();
});
