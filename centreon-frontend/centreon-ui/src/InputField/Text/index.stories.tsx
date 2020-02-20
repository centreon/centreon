import React from 'react';

import TextField from '.';

export default { title: 'InputField/Text' };

export const withLabelAndHelperText = (): JSX.Element => (
  <TextField label="name" helperText="choose a name for current object" />
);

export const withPlaceholderOnly = (): JSX.Element => (
  <TextField placeholder="name" />
);

export const withError = (): JSX.Element => (
  <TextField error label="name" helperText="Wrong name" />
);

export const fullWidth = (): JSX.Element => (
  <TextField fullWidth label="full width" />
);
