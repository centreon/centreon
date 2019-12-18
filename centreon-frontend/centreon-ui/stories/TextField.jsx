import React from 'react';
import { storiesOf } from '@storybook/react';
import TextField from '../src/TextField';

storiesOf('TextField', module).add('with label and helper text', () => (
  <TextField label="name" helperText="choose a name for current object" />
));

storiesOf('TextField', module).add('with placeholder only', () => (
  <TextField placeholder="name" />
));

storiesOf('TextField', module).add('with error', () => (
  <TextField error label="name" helperText="Wrong name" />
));

storiesOf('TextField', module).add('full width', () => (
  <TextField fullWidth label="full width" />
));

storiesOf('TextField', module).add('login / password', () => (
  <div style={{ display: 'flex', flexDirection: 'column', width: 200 }}>
    <TextField label="login" />
    <TextField label="password" type="password" />
  </div>
));
