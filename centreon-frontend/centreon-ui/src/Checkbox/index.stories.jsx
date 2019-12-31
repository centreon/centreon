import React from 'react';

import Checkbox from '.';

export default { title: 'Checkbox' };

export const withTitle = () => <Checkbox label="test" name="test" />;

export const checkedWithTitle = () => (
  <Checkbox label="test" checked name="test" id="test" />
);

export const withoutTitle = () => <Checkbox name="test" />;

export const checkedWithoutTitle = () => <Checkbox checked name="test" />;

export const greenWithoutTitle = () => (
  <Checkbox name="all-hosts" iconColor="green" />
);

export const greenCheckedWithoutTitle = () => (
  <Checkbox name="all-hosts" iconColor="green" checked />
);
