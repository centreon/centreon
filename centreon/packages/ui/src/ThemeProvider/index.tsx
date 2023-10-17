import * as React from 'react';

import { useAtomValue } from 'jotai/utils';
import { equals } from 'ramda';

import {
  ThemeProvider as MuiThemeProvider,
  Theme,
  StyledEngineProvider,
  createTheme,
} from '@mui/material';
import { ThemeOptions } from '@mui/material/styles/createTheme';
import { blue, grey } from '@mui/material/colors';

import { ThemeMode, userAtom } from '@centreon/ui-context';

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

export const getTheme = (mode: ThemeMode): ThemeOptions => ({
  palette: {
    mode,
    ...(equals(mode, ThemeMode.light)
      ? {
          action: {
            acknowledged: '#635a15',
            acknowledgedBackground: '#F7F4E5',
            inDowntime: '#4e1358',
            inDowntimeBackground: '#F9E7FF',
          },
          background: {
            default: '#EDEDED',
          },
          primary: {
            main: '#10069F',
          },
        }
      : {
          action: {
            acknowledged: '#F7F4E5',
            acknowledgedBackground: '#635a15',
            inDowntime: '#F9E7FF',
            inDowntimeBackground: '#4e1358',
          },
          background: {
            default: grey[900],
          },
          primary: {
            main: blue[300],
          },
        }),
    error: {
      main: '#f90026',
    },
    info: {
      main: '#00d3d4',
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
    button: {
      textTransform: 'none',
    },
    caption: {
      fontSize: '0.625rem',
    },
  },
});

interface Props {
  children: React.ReactChild;
}

const ThemeProvider = ({ children }: Props): JSX.Element => {
  const { themeMode } = useAtomValue(userAtom);

  const theme = React.useMemo(
    () => createTheme(getTheme(themeMode || ThemeMode.light)),
    [themeMode],
  );

  return (
    <StyledEngineProvider injectFirst>
      <MuiThemeProvider theme={theme}>{children}</MuiThemeProvider>
    </StyledEngineProvider>
  );
};

export default ThemeProvider;
