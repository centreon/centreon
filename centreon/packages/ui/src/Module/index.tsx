import { QueryClient } from '@tanstack/react-query';
import { Provider as JotaiProvider, createStore } from 'jotai';

import { StylesProvider, createGenerateClassName } from '@mui/styles';

import { QueryProvider, ThemeProvider } from '..';
import SnackbarProvider from '../Snackbar/SnackbarProvider';

export interface ModuleProps {
  children: React.ReactElement;
  maxSnackbars?: number;
  queryClient?: QueryClient;
  seedName: string;
  store: ReturnType<typeof createStore>;
}

const Module = ({
  children,
  seedName,
  maxSnackbars = 3,
  store,
  queryClient
}: ModuleProps): JSX.Element => {
  const generateClassName = createGenerateClassName({
    seed: seedName
  });

  return (
    <QueryProvider queryClient={queryClient}>
      <JotaiProvider store={store}>
        <StylesProvider generateClassName={generateClassName}>
          <ThemeProvider>
            <SnackbarProvider maxSnackbars={maxSnackbars}>
              {children}
            </SnackbarProvider>
          </ThemeProvider>
        </StylesProvider>
      </JotaiProvider>
    </QueryProvider>
  );
};

export default Module;
