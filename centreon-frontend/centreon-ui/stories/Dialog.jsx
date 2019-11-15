import React from 'react';
import { storiesOf } from '@storybook/react';
import {
  ConfirmDialog,
  DuplicateDialog,
  MassiveChangeThresholds,
  ThemeProvider,
} from '../src';

storiesOf('Dialog', module)
  .add('Confirm', () => (
    <ThemeProvider>
      <ConfirmDialog
        open
        onCancel={() => {}}
        labelTitle="Do you want to confirm action ?"
        labelMessage="Your progress will not be saved."
      />
    </ThemeProvider>
  ))
  .add('Duplicate', () => (
    <ThemeProvider>
      <DuplicateDialog open onCancel={() => {}} />
    </ThemeProvider>
  ))
  .add('Massive change thresholds', () => (
    <ThemeProvider>
      <MassiveChangeThresholds open onCancel={() => {}} />
    </ThemeProvider>
  ));
