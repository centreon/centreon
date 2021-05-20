import React from 'react';

import { addDecorator } from '@storybook/react';

import { StylesProvider } from '@material-ui/styles';

import { ThemeProvider } from '../src';

const withStylesProvider = (story) => (
  <StylesProvider injectFirst>{story()}</StylesProvider>
);

addDecorator(withStylesProvider);

const withThemeProvider = (story) => (
  <ThemeProvider>{story()}</ThemeProvider>
);

addDecorator(withThemeProvider);
