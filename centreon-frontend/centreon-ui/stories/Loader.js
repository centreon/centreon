/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { LoaderContent } from '../src';

storiesOf('Loader', module).add('Loader - basic', () => <LoaderContent />, {
  notes: 'A very simple component',
});
