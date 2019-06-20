import React from 'react';
import { storiesOf } from '@storybook/react';
import { Switcher, SwitcherInputField, SwitcherMode } from '../src';

storiesOf('Switcher', module).add('Switcher - regular', () => <Switcher />, {
  notes: 'A very simple component',
});

storiesOf('Switcher', module).add(
  'Switcher - with title',
  () => <Switcher switcherTitle="Status:" />,
  { notes: 'A very simple component' },
);

storiesOf('Switcher', module).add(
  'Switcher - with status',
  () => <Switcher switcherStatus="Not Installed" />,
  { notes: 'A very simple component' },
);

storiesOf('Switcher', module).add(
  'Switcher - input field',
  () => <SwitcherInputField />,
  { notes: 'A very simple component' },
);

storiesOf('Switcher', module).add('Switcher - mode', () => <SwitcherMode />, {
  notes: 'A very simple component',
});
