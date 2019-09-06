/* eslint-disable no-alert */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { ConfirmationDialog, PromptDialog } from '../src';

storiesOf('Dialog', module)
  .add('Prompt', () => (
    <PromptDialog active title="Prompt" info="Please enter a number" />
  ))
  .add('Confirmation', () => (
    <ConfirmationDialog active title="Confirm" info="Are you sure?" />
  ));
