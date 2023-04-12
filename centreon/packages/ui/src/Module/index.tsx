import * as React from 'react';

import { Provider as JotaiProvider, createStore } from 'jotai';

import { createGenerateClassName, StylesProvider } from '@mui/styles';

import { ThemeProvider } from '..';
import SnackbarProvider from '../Snackbar/SnackbarProvider';

export interface ModuleProps {
  children: React.ReactElement;
  maxSnackbars: number;
  seedName: string;
  store: ReturnType<typeof createStore>;
}

const Module = ({
  children,
  seedName,
  maxSnackbars,
  store
}: ModuleProps): JSX.Element => {
  const generateClassName = createGenerateClassName({
    seed: seedName
  });

  return (
    <JotaiProvider store={store}>
      <StylesProvider generateClassName={generateClassName}>
        <ThemeProvider>
          <SnackbarProvider maxSnackbars={maxSnackbars}>
            {children}
          </SnackbarProvider>
        </ThemeProvider>
      </StylesProvider>
    </JotaiProvider>
  );
};

export default Module;
