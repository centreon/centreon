import React from 'react';
import { storiesOf } from '@storybook/react';
import { Pagination } from '../src';

storiesOf('Pagination', module).add(
  'Pagination - with numbers',
  () => <Pagination />,
  { notes: 'A very simple component' },
);
