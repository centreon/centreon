/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/extensions */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { ListSortable } from '../src';
import Provider from '../Provider.js';
import configureStore from '../configureStore.js';

const store = configureStore;

const withProvider = (story) => <Provider store={store}>{story()}</Provider>;

storiesOf('List', module)
  .addDecorator(withProvider)
  .add('List - sortable', () => <ListSortable />, {
    notes: 'A very simple component',
  });
