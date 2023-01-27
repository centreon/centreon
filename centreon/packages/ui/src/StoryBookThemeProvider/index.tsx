import * as React from 'react';
import { useMemo } from 'react';

import {
  ThemeProvider as MuiThemeProvider,
  StyledEngineProvider,
  createTheme,
  CssBaseline
} from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import { getTheme } from '../ThemeProvider';

interface Props {
  children: React.ReactElement;
  themeMode: ThemeMode;
}

const StoryBookThemeProvider = ({
  children,
  themeMode
}: Props): JSX.Element => {
  const theme = useMemo(() => createTheme(getTheme(themeMode)), [themeMode]);

  return (
    <StyledEngineProvider injectFirst>
      <MuiThemeProvider theme={theme}>
        {children}
        <CssBaseline />
      </MuiThemeProvider>
    </StyledEngineProvider>
  );
};

export default StoryBookThemeProvider;
