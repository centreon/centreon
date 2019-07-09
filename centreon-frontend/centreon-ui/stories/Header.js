/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { Header } from '../src';

storiesOf('Header', module).add('Header - without content', () => (
  <Header style={{ minHeight: '53px' }} />
));
