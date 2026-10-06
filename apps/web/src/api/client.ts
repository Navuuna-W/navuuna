// openapi-fetch client, typed against the generated schema. One shared instance for the
// whole app so the base URL and credentials are set in one place.
//
// Base URL: same origin by default. When Laravel is reachable, VITE_API_BASE_URL points at
// it. The dev MVT/API mock lives at the same origin, so dev needs no override.

import createClient from 'openapi-fetch';
import type { paths } from './schema';

export const apiClient = createClient<paths>({
  baseUrl: import.meta.env.VITE_API_BASE_URL ?? '/api/v1',
  credentials: 'include',
});
