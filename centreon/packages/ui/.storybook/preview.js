import React from 'react';

import { addDecorator } from '@storybook/react';

import { useDarkMode } from 'storybook-dark-mode';

import { ThemeMode } from '@centreon/ui-context';

import { StoryBookThemeProvider } from '../src';


const withThemeProvider = (story) => (
  <StoryBookThemeProvider themeMode={useDarkMode() ? ThemeMode.dark :  ThemeMode.light}>{story()}</StoryBookThemeProvider>
);

addDecorator(withThemeProvider);
