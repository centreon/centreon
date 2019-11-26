import React from 'react';
import { storiesOf } from '@storybook/react';
import {
  ConfirmDialog,
  DuplicateDialog,
  MassiveChangeThresholds,
} from '../src';

storiesOf('Dialog', module)
  .add('Confirm', () => (
    <ConfirmDialog
      open
      onCancel={() => {}}
      labelTitle="Do you want to confirm action ?"
      labelMessage="Your progress will not be saved."
    />
  ))
  .add('Duplicate', () => <DuplicateDialog open onCancel={() => {}} />)
  .add('Massive change thresholds', () => (
    <MassiveChangeThresholds open onCancel={() => {}} />
  ));
