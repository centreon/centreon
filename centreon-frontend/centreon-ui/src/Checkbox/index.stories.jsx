import React from 'react';

import Checkbox from '.';

export default { title: 'Checkbox' };

export const withTitle = () => <Checkbox label="test" name="test" />;

export const checkedWithTitle = () => (
  <Checkbox checked id="test" label="test" name="test" />
);

export const withoutTitle = () => <Checkbox name="test" />;

export const checkedWithoutTitle = () => <Checkbox checked name="test" />;

export const greenWithoutTitle = () => (
  <Checkbox iconColor="green" name="all-hosts" />
);

export const greenCheckedWithoutTitle = () => (
  <Checkbox checked iconColor="green" name="all-hosts" />
);
