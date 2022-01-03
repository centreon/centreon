import React from 'react';

import { addDecorator } from '@storybook/react';

import { ThemeProvider } from '../src';

const withThemeProvider = (story) => (
  <ThemeProvider>{story()}</ThemeProvider>
);

addDecorator(withThemeProvider);
