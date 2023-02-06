import * as React from 'react';

import { Provider as JotaiProvider } from 'jotai';

import { createGenerateClassName, StylesProvider } from '@mui/styles';

import { ThemeProvider } from '..';
import SnackbarProvider from '../Snackbar/SnackbarProvider';

export interface ModuleProps {
  children: React.ReactElement;
  maxSnackbars: number;
  seedName: string;
}

const Module = ({
  children,
  seedName,
  maxSnackbars
}: ModuleProps): JSX.Element => {
  const generateClassName = createGenerateClassName({
    seed: seedName
  });

  return (
    <JotaiProvider>
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
