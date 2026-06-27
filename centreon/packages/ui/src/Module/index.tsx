import type { PaletteOptions } from '@mui/material';

import type { QueryClient } from '@tanstack/react-query';
import { type createStore, Provider as JotaiProvider } from 'jotai';

import { QueryProvider, ThemeProvider } from '..';
import SnackbarProvider from '../Snackbar/SnackbarProvider';

export interface ModuleProps {
  children: React.ReactElement;
  maxSnackbars?: number;
  queryClient?: QueryClient;
  seedName: string;
  store: ReturnType<typeof createStore>;
  overrideTheme?: {
    light: Partial<PaletteOptions>;
    dark: Partial<PaletteOptions>;
  };
}

const Module = ({
  children,
  maxSnackbars = 3,
  store,
  queryClient,
  overrideTheme
}: ModuleProps): JSX.Element => {
  return (
    <QueryProvider queryClient={queryClient}>
      <JotaiProvider store={store}>
        <ThemeProvider overrideTheme={overrideTheme}>
          <SnackbarProvider maxSnackbars={maxSnackbars}>
            {children}
          </SnackbarProvider>
        </ThemeProvider>
      </JotaiProvider>
    </QueryProvider>
  );
};

export default Module;
