import { ReactElement } from 'react';

import {
  render as rtlRender,
  RenderOptions,
  RenderResult
} from '@testing-library/react';
import fetchMock, { MockParams } from 'jest-fetch-mock';

import { ThemeMode } from '@centreon/ui-context';

import ThemeProvider from './StoryBookThemeProvider';

interface Props {
  children: ReactElement;
}

const ThemeProviderWrapper = ({ children }: Props): JSX.Element => {
  return <ThemeProvider themeMode={ThemeMode.light}>{children}</ThemeProvider>;
};

const render = (ui: ReactElement, options?: RenderOptions): RenderResult =>
  rtlRender(ui, {
    wrapper: ThemeProviderWrapper as (props) => ReactElement | null,
    ...options
  });

// re-export everything
export * from '@testing-library/react';

// override render method
export { render };

interface MockResponse {
  data: object | unknown;
  options?: MockParams;
}

export const mockResponse = ({ data, options }: MockResponse): void => {
  fetchMock.mockResponse(JSON.stringify(data), options);
};

export const mockResponseOnce = ({ data, options }: MockResponse): void => {
  fetchMock.once(JSON.stringify(data), options);
};

export const resetMocks = (): void => {
  fetchMock.resetMocks();
};

export const getFetchCall = (index: number): string | Request | undefined => {
  return fetchMock.mock.calls[index][0];
};
