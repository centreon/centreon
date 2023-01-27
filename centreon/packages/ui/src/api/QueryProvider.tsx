import { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const defaultCacheTime = 5 * 1_000;

const client = new QueryClient({
  defaultOptions: {
    queries: {
      cacheTime: defaultCacheTime,
      refetchOnWindowFocus: false,
      staleTime: defaultCacheTime,
      suspense: true
    }
  }
});

interface Props {
  children: ReactNode;
}

const QueryProvider = ({ children }: Props): JSX.Element => (
  <QueryClientProvider client={client}>{children}</QueryClientProvider>
);

export default QueryProvider;
