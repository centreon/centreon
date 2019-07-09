/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { RadioButton } from '../src';

storiesOf('Radio Button', module).add(
  'Radio Button - with title',
  () => <RadioButton label="test" name="test" />,
  { notes: 'A very simple component' },
);

storiesOf('Radio Button', module).add(
  'Radio Button Checked - with title',
  () => <RadioButton label="test" checked name="test" />,
  { notes: 'A very simple component' },
);

storiesOf('Radio Button', module).add(
  'Radio Button - without title',
  () => <RadioButton name="test" />,
  { notes: 'A very simple component' },
);

storiesOf('Radio Button', module).add(
  'Radio Button Checked - without title',
  () => <RadioButton checked name="test" />,
  { notes: 'A very simple component' },
);

storiesOf('Radio Button', module).add(
  'Radio Button green - without title',
  () => <RadioButton name="test" iconColor="green" />,
  { notes: 'A very simple component' },
);

storiesOf('Radio Button', module).add(
  'Radio Button Checked green - without title',
  () => <RadioButton name="test" checked iconColor="green" />,
  { notes: 'A very simple component' },
);
