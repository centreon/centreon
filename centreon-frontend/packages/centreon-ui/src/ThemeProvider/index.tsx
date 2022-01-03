import React from 'react';

import {
  ThemeProvider as MuiThemeProvider,
  Theme,
  StyledEngineProvider,
  createTheme,
  adaptV4Theme,
} from '@mui/material';

declare module '@mui/styles/defaultTheme' {
  // eslint-disable-next-line @typescript-eslint/no-empty-interface
  interface DefaultTheme extends Theme {}
}

declare module '@mui/material/styles/createPalette' {
  interface TypeAction {
    acknowledged: string;
    acknowledgedBackground: string;
    inDowntime: string;
    inDowntimeBackground: string;
  }
}

const theme = createTheme(
  adaptV4Theme({
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
  }),
);

interface Props {
  children: React.ReactChild;
}

const ThemeProvider = ({ children }: Props): JSX.Element => (
  <StyledEngineProvider injectFirst>
    <MuiThemeProvider theme={theme}>{children}</MuiThemeProvider>
  </StyledEngineProvider>
);

export default ThemeProvider;
