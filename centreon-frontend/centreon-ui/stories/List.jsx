import React from 'react';
import { storiesOf } from '@storybook/react';
import { ListSortable } from '../src';
import Provider from '../Provider';
import configureStore from '../configureStore';

const store = configureStore;

const withProvider = (story) => <Provider store={store}>{story()}</Provider>;

storiesOf('List', module)
  .addDecorator(withProvider)
  .add('List - sortable', () => <ListSortable />, {
    notes: 'A very simple component',
  });
