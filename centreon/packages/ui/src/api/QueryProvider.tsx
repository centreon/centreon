import { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const defaultCacheTime = 5 * 1_000;

export const client = new QueryClient({
  defaultOptions: {
    queries: {
      gcTime: defaultCacheTime,
      refetchOnWindowFocus: false,
      staleTime: defaultCacheTime,
      suspense: true
    }
  }
});

interface Props {
  children: ReactNode;
  queryClient?: QueryClient;
}

const QueryProvider = ({ children, queryClient }: Props): JSX.Element => (
  <QueryClientProvider client={queryClient || client}>
    {children}
  </QueryClientProvider>
);

export default QueryProvider;
