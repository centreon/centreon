/* eslint-disable no-alert */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { ConfirmationDialog, ErrorDialog, PromptDialog } from '../src';

storiesOf('Dialog', module)
  .add('Error', () => (
    <ErrorDialog
      open
      title="Error"
      info="Something unexpected happened..."
      confirmLabel="OK"
      onClose={() => alert("I've been closed")}
    />
  ))
  .add('Prompt', () => (
    <PromptDialog active title="Prompt" info="Please enter a number" />
  ))
  .add('Confirmation', () => (
    <ConfirmationDialog active title="Confirm" info="Are you sure?" />
  ));
