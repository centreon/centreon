import { ThemeMode } from '@centreon/ui-context';

import {
  type RenderOptions,
  type RenderResult,
  render as rtlRender
} from '@testing-library/react';
import type { ReactElement, ReactNode } from 'react';

import ThemeProvider from '../packages/ui/src/StoryBookThemeProvider';

/**
 * Minimal render helper mirroring `packages/ui/test/testRenderer` but without
 * the jest-fetch-mock coupling, so it works under Rstest. Wraps components in
 * the MUI theme provider, like the real app.
 */
const Wrapper = ({ children }: { children: ReactNode }): JSX.Element => (
  <ThemeProvider themeMode={ThemeMode.light}>
    {children as ReactElement}
  </ThemeProvider>
);

export const render = (
  ui: ReactElement,
  options?: RenderOptions
): RenderResult => rtlRender(ui, { wrapper: Wrapper, ...options });

export * from '@testing-library/react';
