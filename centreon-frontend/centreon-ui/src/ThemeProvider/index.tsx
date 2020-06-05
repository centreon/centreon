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
    primary: {
      main: '#10069F',
    },
    background: {
      default: '#EDEDED',
    },
    warning: {
      main: '#FF9A13',
    },
    success: {
      main: '#84BD00',
    },
    error: {
      main: '#f90026',
    },
    info: {
      main: '#00d3d4',
    },
    action: {
      acknowledged: '#AA9C24',
      acknowledgedBackground: '#F7F4E5',
      inDowntime: '#9C27B0',
      inDowntimeBackground: '#F9E7FF',
    },
  },
  typography: {
    body2: {
      fontSize: '0.75rem',
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
