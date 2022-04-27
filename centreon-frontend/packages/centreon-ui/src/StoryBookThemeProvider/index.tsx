import React from 'react';

import {
  ThemeProvider as MuiThemeProvider,
  StyledEngineProvider,
  createTheme,
} from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import { getTheme } from '../ThemeProvider';

interface Props {
  children: React.ReactChild;
  themeMode: ThemeMode;
}

const StoryBookThemeProvider = ({
  children,
  themeMode,
}: Props): JSX.Element => {
  const theme = React.useMemo(
    () => createTheme(getTheme(themeMode)),
    [themeMode],
  );

  return (
    <StyledEngineProvider injectFirst>
      <MuiThemeProvider theme={theme}>{children}</MuiThemeProvider>
    </StyledEngineProvider>
  );
};

export default StoryBookThemeProvider;
