import React from 'react';

import TextField from '.';

export default { title: 'InputField/Text' };

export const withLabelAndHelperText = () => (
  <TextField label="name" helperText="choose a name for current object" />
);

export const withPlaceholderOnly = () => <TextField placeholder="name" />;

export const withError = () => (
  <TextField error label="name" helperText="Wrong name" />
);

export const fullWidth = () => <TextField fullWidth label="full width" />;
