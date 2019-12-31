import React from 'react';

import InputField from '.';

export default { title: 'InputField' };

export const smallWithTitle = () => (
  <InputField
    type="text"
    label="Input field with title"
    name="test"
    inputSize="small"
  />
);

export const smallWithoutTitle = () => (
  <InputField type="text" name="test" inputSize="small" />
);

export const errorWithoutTitle = () => (
  <InputField
    type="text"
    name="test"
    error="The field is mandatory"
    inputSize="small"
  />
);
