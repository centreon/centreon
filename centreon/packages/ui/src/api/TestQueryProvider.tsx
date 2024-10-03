import { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const client = new QueryClient({
  defaultOptions: {
    queries: {
      gcTime: 0,
      retry: false
    }
  }
});

interface TestQueryProviderProps {
  children: ReactNode;
}

const TestQueryProvider = ({
  children
}: TestQueryProviderProps): JSX.Element => (
  <QueryClientProvider client={client}>{children}</QueryClientProvider>
);

export default TestQueryProvider;
