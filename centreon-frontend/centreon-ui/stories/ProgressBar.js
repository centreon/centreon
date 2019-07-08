/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { ProgressBar, ProgressBarItem } from '../src';

storiesOf('Progress Bar', module).add(
  'Progress Bar - with numbers',
  () => (
    <ProgressBar>
      <ProgressBarItem classActive="active" number="1" />
      <ProgressBarItem number="2" />
      <ProgressBarItem number="3" />
    </ProgressBar>
  ),
  { notes: 'A very simple component' },
);
