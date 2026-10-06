// ESLint 9 flat config for apps/web (ADR-002). Keep it short and strict:
// typescript-eslint strict preset, react-hooks and jsx-a11y, and Prettier compatibility
// handled by running `prettier --check` separately in the lint script.

import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import reactHooks from 'eslint-plugin-react-hooks';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import globals from 'globals';

export default tseslint.config(
  {
    ignores: ['dist', 'coverage', 'node_modules', 'public/basemap', 'src/api/schema.d.ts'],
  },
  js.configs.recommended,
  ...tseslint.configs.strict,
  ...tseslint.configs.stylistic,
  {
    files: ['src/**/*.{ts,tsx}', 'vite-plugins/**/*.ts'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
      },
      parserOptions: {
        ecmaFeatures: { jsx: true },
      },
    },
    plugins: {
      'react-hooks': reactHooks,
      'jsx-a11y': jsxA11y,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      ...jsxA11y.flatConfigs.recommended.rules,
      // Bible §14.5: no `any` anywhere in src.
      '@typescript-eslint/no-explicit-any': 'error',
      // Make unused code loud so no half-finished scaffolding slips to main.
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
    },
  },
  {
    files: ['vite.config.ts', 'vitest.config.ts', 'scripts/**/*.{js,ts,mjs}'],
    languageOptions: {
      globals: { ...globals.node },
    },
  },
  {
    files: ['playwright.config.ts', 'e2e/**/*.{ts,tsx}'],
    languageOptions: {
      globals: { ...globals.node, ...globals.browser },
    },
  },
  {
    files: ['src/**/*.test.{ts,tsx}', 'src/test/**/*.{ts,tsx}'],
    languageOptions: {
      globals: { ...globals.browser, ...globals.node },
    },
  }
);
