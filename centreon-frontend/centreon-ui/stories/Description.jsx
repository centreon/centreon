import React from 'react';
import { storiesOf } from '@storybook/react';
import { Description } from '../src';

storiesOf('Description', module).add(
  'Description - content date',
  () => <Description date="Description content date 12/7/2018" />,
  { notes: 'A very simple component' },
);
storiesOf('Description', module).add(
  'Description - content title',
  () => <Description title="Description content title" />,
  { notes: 'A very simple component' },
);
storiesOf('Description', module).add(
  'Description - content text',
  () => (
    <Description text="Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum." />
  ),
  { notes: 'A very simple component' },
);
storiesOf('Description', module).add(
  'Description - content note',
  () => <Description note="Release note of v 3.11.5 available here >" />,
  { notes: 'A very simple component' },
);
