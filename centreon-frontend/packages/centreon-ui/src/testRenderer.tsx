import React, { ReactElement } from 'react';

import { Provider as JotaiProvider } from 'jotai';
import {
  render as rtlRender,
  RenderOptions,
  RenderResult,
} from '@testing-library/react';

import { ThemeMode } from '@centreon/ui-context';

import ThemeProvider from './StoryBookThemeProvider';

interface Props {
  children: React.ReactChild;
}

const ThemeProviderWrapper = ({ children }: Props): JSX.Element => {
  return (
    <JotaiProvider scope="ui-context">
      <ThemeProvider themeMode={ThemeMode.light}>{children}</ThemeProvider>
    </JotaiProvider>
  );
};

const render = (
  ui: React.ReactElement,
  options?: RenderOptions,
): RenderResult =>
  rtlRender(ui, {
    wrapper: ThemeProviderWrapper as (props) => ReactElement | null,
    ...options,
  });

// re-export everything
export * from '@testing-library/react';

// override render method
export { render };
