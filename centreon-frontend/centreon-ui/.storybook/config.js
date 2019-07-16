import React from 'react';
import { configure, addDecorator } from '@storybook/react';
import { withNotes } from '@storybook/addon-notes';
import { StylesProvider } from '@material-ui/styles';

addDecorator(withNotes);

const withStylesProvider = (story) => (
  <StylesProvider injectFirst>{story()}</StylesProvider>
);

addDecorator(withStylesProvider);

function loadStories() {
  require('../stories/index.js');
}

configure(loadStories, module);
