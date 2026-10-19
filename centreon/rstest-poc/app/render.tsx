import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  type RenderOptions,
  type RenderResult,
  render as rtlRender
} from '@testing-library/react';
import { Provider as JotaiProvider } from 'jotai';
import type { ReactElement, ReactNode } from 'react';

import ThemeProvider from '../../packages/ui/src/ThemeProvider';

/**
 * App-level render harness, mirroring the providers the Cypress `cy.mount`
 * relies on (MUI ThemeProvider) plus the ones real app components need at
 * runtime: a fresh React Query client and a Jotai store. This is the Rstest
 * equivalent of the Cypress component mount.
 */
export const renderApp = (
  ui: ReactElement,
  options?: RenderOptions
): RenderResult => {
  const queryClient = new QueryClient({
    defaultOptions: {
      mutations: { retry: false },
      queries: { retry: false }
    }
  });

  const Wrapper = ({ children }: { children: ReactNode }): JSX.Element => (
    <JotaiProvider>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider>{children as ReactElement}</ThemeProvider>
      </QueryClientProvider>
    </JotaiProvider>
  );

  return rtlRender(ui, { wrapper: Wrapper, ...options });
};

export * from '@testing-library/react';
