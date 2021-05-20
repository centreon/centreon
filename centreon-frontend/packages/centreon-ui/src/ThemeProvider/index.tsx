import React from 'react';

import {
  createMuiTheme,
  ThemeProvider as MuiThemeProvider,
  ThemeProviderProps,
} from '@material-ui/core';

declare module '@material-ui/core/styles/createPalette' {
  interface TypeAction {
    acknowledged: string;
    acknowledgedBackground: string;
    inDowntime: string;
    inDowntimeBackground: string;
  }
}

const theme = createMuiTheme({
  palette: {
    action: {
      acknowledged: '#AA9C24',
      acknowledgedBackground: '#F7F4E5',
      inDowntime: '#9C27B0',
      inDowntimeBackground: '#F9E7FF',
    },
    background: {
      default: '#EDEDED',
    },
    error: {
      main: '#f90026',
    },
    info: {
      main: '#00d3d4',
    },
    primary: {
      main: '#10069F',
    },
    success: {
      main: '#84BD00',
    },
    warning: {
      main: '#FF9A13',
    },
  },
  typography: {
    body1: {
      fontSize: '0.875rem',
    },
    body2: {
      fontSize: '0.75rem',
    },
    caption: {
      fontSize: '0.625rem',
    },
  },
});

type Props = Omit<ThemeProviderProps, 'theme'>;

const ThemeProvider = ({ children, ...rest }: Props): JSX.Element => (
  <MuiThemeProvider theme={theme} {...rest}>
    {children}
  </MuiThemeProvider>
);

export default ThemeProvider;
