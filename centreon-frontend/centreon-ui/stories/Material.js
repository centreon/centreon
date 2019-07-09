/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import Button from '@material-ui/core/Button';
import {
  MaterialTabs,
  MaterialTable,
  MaterialProgressBar,
  MaterialButton,
} from '../src';

storiesOf('Material', module).add(
  'Material - test components',
  () => (
    <React.Fragment>
      <Button variant="contained" color="primary">
        Hello World
      </Button>
      <br />
      <br />
      <MaterialTabs />
      <br />
      <br />
      <MaterialTable />
      <br />
      <br />
      <MaterialProgressBar />
      <br />
      <br />
      <MaterialButton />
    </React.Fragment>
  ),
  { notes: 'A very simple component' },
);
