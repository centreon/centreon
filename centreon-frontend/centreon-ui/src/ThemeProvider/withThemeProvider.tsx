import * as React from 'react';

import ThemeProvider from '.';

const withThemeProvider = <TProps extends Record<string, string>>(
  Component: (props) => JSX.Element,
) => {
  return (props: TProps): JSX.Element => {
    return (
      <ThemeProvider>
        <Component {...props} />
      </ThemeProvider>
    );
  };
};

export default withThemeProvider;
