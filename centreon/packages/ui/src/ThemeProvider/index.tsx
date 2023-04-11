import * as React from 'react';

import { useAtomValue } from 'jotai';
import { CSSInterpolation } from 'tss-react';
import { equals } from 'ramda';

import {
  ThemeProvider as MuiThemeProvider,
  Theme,
  StyledEngineProvider,
  createTheme,
  InputBaseProps,
  ButtonProps
} from '@mui/material';
import CssBaseline from '@mui/material/CssBaseline';
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
    large: true;
  }
}

const getInputBaseRootStyle = ({
  size,
  multiline
}: InputBaseProps): CSSInterpolation => {
  if (multiline) {
    return {
      padding: '0px'
    };
  }

  if (equals(size, 'compact')) {
    return {
      padding: '8px 8px',
      paddingRight: '0px'
    };
  }
  if (equals(size, 'small')) {
    return {
      padding: '8.5px 14px',
      paddingRight: '0px'
    };
  }
  if (equals(size, 'large')) {
    return {
      padding: '14px 18px',
      paddingRight: '0px'
    };
  }

  return {
    padding: '10px 15.5px',
    paddingRight: '0px',
    width: 'auto'
  };
};

const getInputBaseInputStyle = ({ size }: InputBaseProps): CSSInterpolation => {
  if (equals(size, 'compact')) {
    return {
      fontSize: 'x-small',
      minHeight: '32px'
    };
  }
  if (equals(size, 'small')) {
    return {
      minHeight: '36px'
    };
  }
  if (equals(size, 'large')) {
    return {
      minHeight: '48px'
    };
  }

  return {
    minHeight: '40px'
  };
};

const getButtonRootStyle = ({ size }: ButtonProps): CSSInterpolation => {
  if (equals(size, 'medium')) {
    return {
      height: '40px'
    };
  }
  if (equals(size, 'large')) {
    return {
      height: '48px'
    };
  }

  return {
    height: '36px'
  };
};

export const getTheme = (mode: ThemeMode): ThemeOptions => ({
  components: {
    MuiButton: {
      defaultProps: { size: 'small' },
      styleOverrides: {
        root: ({ ownerState }) => getButtonRootStyle(ownerState)
      }
    },
    MuiChip: {
      styleOverrides: {
        root: ({ ownerState, theme }) => ({
          ...(equals(ownerState.size, 'medium') && {
            borderRadius: theme.spacing(1.25),
            fontSize: theme.typography.body2.fontSize,
            height: theme.spacing(2.5),
            lineHeight: theme.spacing(2.5),
            minWidth: theme.spacing(2.5)
          }),
          ...(equals(ownerState.size, 'small') && {
            borderRadius: theme.spacing(0.75),
            fontSize: theme.typography.caption.fontSize,
            height: theme.spacing(1.5),
            lineHeight: theme.spacing(1.5),
            minWidth: theme.spacing(1.5)
          }),
          '& .MuiChip-label': {
            '&:empty': {
              display: 'none'
            },
            lineHeight: 1
          }
        })
      }
    },
    MuiCssBaseline: {
      styleOverrides: (theme) => `
        ::-webkit-scrollbar {
          height: ${theme.spacing(1)};
          width: ${theme.spacing(1)};
          background-color: ${theme.palette.background.default};
        }
        ::-webkit-scrollbar-thumb {
          background-color: ${
            equals(mode, 'dark')
              ? theme.palette.divider
              : theme.palette.text.disabled
          };
          border-radius: ${theme.spacing(0.5)};
        }
        ::-webkit-scrollbar-thumb:hover {
          background-color: ${theme.palette.primary.main};
        }
        * {
          scrollbar-color: ${
            equals(mode, 'dark')
              ? theme.palette.divider
              : theme.palette.text.disabled
          } ${theme.palette.background.default};
          scrollbar-width: thin;
        }
        html {
          margin: 0;
          padding: 0;
          width: 100%;
          height: 100%;
        }
        body {
          background-color: ${theme.palette.background.paper};
          height: 100%;
          padding: 0;
          width: 100%;
        }
      `
    },
    MuiInputBase: {
      styleOverrides: {
        root: ({ ownerState }) => getInputBaseInputStyle(ownerState)
      }
    },
    MuiList: {
      styleOverrides: {
        root: () => ({
          '&.MuiMenu-list': {
            paddingBottom: 0,
            paddingTop: 0
          }
        })
      }
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
                : theme.palette.primary.main
            },
          fontSize: theme.typography.body2.fontSize
        })
      }
    },
    MuiOutlinedInput: {
      styleOverrides: {
        input: ({ ownerState }) => getInputBaseRootStyle(ownerState)
      }
    },
    MuiPaper: {
      defaultProps: {
        variant: 'outlined'
      },
      styleOverrides: {
        root: ({ theme }) => ({
          [`[role="tooltip"] &, &.MuiMenu-paper, &.${autocompleteClasses.paper}`]:
            {
              backgroundColor: theme.palette.background.default,
              border: 'none',
              borderRadius: 0,
              boxShadow: theme.shadows[3]
            }
        })
      }
    },
    MuiTextField: {
      defaultProps: {
        variant: 'outlined'
      }
    }
  },
  palette: getPalette(mode),
  typography: {
    body1: {
      fontSize: '0.875rem'
    },
    body2: {
      fontSize: '0.75rem'
    },
    button: {
      textTransform: 'none'
    },
    caption: {
      fontSize: '0.625rem'
    }
  }
});

interface Props {
  children: React.ReactNode;
}

const ThemeProvider = ({ children }: Props): JSX.Element => {
  const { themeMode } = useAtomValue(userAtom);

  const theme = React.useMemo(
    () => createTheme(getTheme(themeMode || ThemeMode.light)),
    [themeMode]
  );

  return (
    <StyledEngineProvider injectFirst>
      <MuiThemeProvider theme={theme}>
        <CssBaseline />
        {children}
      </MuiThemeProvider>
    </StyledEngineProvider>
  );
};

export default ThemeProvider;
