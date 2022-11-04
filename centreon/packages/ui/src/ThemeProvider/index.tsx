import * as React from 'react';

import { useAtomValue } from 'jotai/utils';
import { CSSInterpolation } from 'tss-react/types';
import { equals } from 'ramda';

import {
  ThemeProvider as MuiThemeProvider,
  Theme,
  StyledEngineProvider,
  createTheme,
  InputBaseProps,
  ButtonProps,
} from '@mui/material';
import { autocompleteClasses } from '@mui/material/Autocomplete';
import { ThemeOptions } from '@mui/material/styles/createTheme';

import { ThemeMode, userAtom } from '@centreon/ui-context';

import { getPalette } from './palettes';

declare module '@mui/styles/defaultTheme' {
  // eslint-disable-next-line @typescript-eslint/no-empty-interface
  interface DefaultTheme extends Theme {}
}

declare module '@mui/material/TextField' {
  interface TextFieldPropsSizeOverrides {
    compact: true;
  }
}

const getInputBaseRootStyle = ({ size }: InputBaseProps): CSSInterpolation => {
  if (equals(size, 'compact')) {
    return {
      fontSize: 'x-small',
      minHeight: '32px',
    };
  }
  if (equals(size, 'small')) {
    return {
      minHeight: '36px',
    };
  }

  return {
    minHeight: '48px',
  };
};

const getButtonRootStyle = ({ size }: ButtonProps): CSSInterpolation => {
  if (equals(size, 'small')) {
    return {
      height: '36px',
    };
  }
  if (equals(size, 'large')) {
    return {
      height: '48px',
    };
  }

  return {
    height: '40px',
  };
};

export const getTheme = (mode: ThemeMode): ThemeOptions => ({
  components: {
    MuiButton: {
      styleOverrides: {
        root: ({ ownerState }) => getButtonRootStyle(ownerState),
      },
    },
    MuiChip: {
      styleOverrides: {
        root: ({ ownerState, theme }) => ({
          ...(equals(ownerState.size, 'medium') && {
            borderRadius: theme.spacing(1.25),
            fontSize: theme.typography.body2.fontSize,
            height: theme.spacing(2.5),
            lineHeight: theme.spacing(2.5),
            minWidth: theme.spacing(2.5),
          }),
          ...(equals(ownerState.size, 'small') && {
            borderRadius: theme.spacing(0.75),
            fontSize: theme.typography.caption.fontSize,
            height: theme.spacing(1.5),
            lineHeight: theme.spacing(1.5),
            minWidth: theme.spacing(1.5),
          }),
          '& .MuiChip-label': {
            '&:empty': {
              display: 'none',
            },
            lineHeight: 1,
          },
        }),
      },
    },
    MuiInputBase: {
      styleOverrides: {
        root: ({ ownerState }) => getInputBaseRootStyle(ownerState),
      },
    },
    MuiList: {
      styleOverrides: {
        root: () => ({
          '&.MuiMenu-list': {
            paddingBottom: 0,
            paddingTop: 0,
          },
        }),
      },
    },
    MuiMenuItem: {
      styleOverrides: {
        root: ({ theme }) => ({
          '&:hover, &.Mui-selected, &.Mui-selected:hover, &.Mui-selected:focus':
            {
              background: equals(theme.palette.mode, ThemeMode.dark)
                ? theme.palette.primary.dark
                : theme.palette.primary.light,
              color: equals(theme.palette.mode, ThemeMode.dark)
                ? theme.palette.common.white
                : theme.palette.primary.main,
            },
          fontSize: theme.typography.body2.fontSize,
        }),
      },
    },
    MuiPaper: {
      defaultProps: {
        variant: 'outlined',
      },
      styleOverrides: {
        root: ({ theme }) => ({
          [`[role="tooltip"] &, &.MuiMenu-paper, &.${autocompleteClasses.paper}`]:
            {
              backgroundColor: theme.palette.background.default,
              border: 'none',
              borderRadius: 0,
              boxShadow: theme.shadows[3],
            },
        }),
      },
    },
    MuiTextField: {
      defaultProps: {
        variant: 'outlined',
      },
    },
  },
  palette: getPalette(mode),
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
  children: React.ReactNode;
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
