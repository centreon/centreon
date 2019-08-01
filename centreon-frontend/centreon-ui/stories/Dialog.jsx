/* eslint-disable no-alert */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { ErrorDialog } from '../src';

storiesOf('Dialog', module).add('Error', () => (
  <ErrorDialog
    open
    title="Error"
    text="Something unexpected happened..."
    confirmLabel="Close"
    onClose={() => alert("I've been closed")}
  />
));
