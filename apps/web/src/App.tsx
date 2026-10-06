// Root of the app: query client, router, banner, header, map route.

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { IllustrativeBanner } from '@/ui/IllustrativeBanner';
import { MapRoute } from '@/routes/MapRoute';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      staleTime: 30_000,
    },
  },
});

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <div className="flex min-h-screen flex-col">
          <IllustrativeBanner />
          <header className="border-b border-neutral-200 bg-white px-4 py-2">
            <h1 className="text-base font-semibold">Navuuna — Nairobi</h1>
          </header>
          <Routes>
            <Route path="/" element={<MapRoute />} />
          </Routes>
        </div>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
