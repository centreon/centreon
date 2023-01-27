import { addDecorator } from '@storybook/react';

import { useDarkMode } from 'storybook-dark-mode';

import { ThemeMode } from '@centreon/ui-context';

import StoryBookThemeProvider from '../src/StoryBookThemeProvider';
import QueryProvider from '../src/api/QueryProvider';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';


const withThemeProvider = (story): JSX.Element => (
  <StoryBookThemeProvider themeMode={useDarkMode() ? ThemeMode.dark :  ThemeMode.light}>
    <QueryProvider>
      {story()}
      <ReactQueryDevtools />
    </QueryProvider>
  </StoryBookThemeProvider>
);

addDecorator(withThemeProvider);
