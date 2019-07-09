/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { TableCounter } from '../src';

storiesOf('Table Counter', module).add(
  'Table counter',
  () => <TableCounter number="20" />,
  {
    notes: 'A very simple component',
  },
);

storiesOf('Table Counter', module).add(
  'Table counter - active',
  () => <TableCounter activeClass="active" number="20" />,
  {
    notes: 'A very simple component',
  },
);
