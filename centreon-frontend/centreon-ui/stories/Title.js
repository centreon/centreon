import React from 'react';
import { storiesOf } from '@storybook/react';
import { Title } from '../src';

storiesOf('Title', module).add('Title - custom', () => <Title label="Test" />, {
  notes: 'A very simple component',
});
storiesOf('Title', module).add(
  'Title - custom host',
  () => <Title titleColor="host" label="Host" />,
  {
    notes: 'A very simple component',
  },
);
storiesOf('Title', module).add(
  'Title - custom bam',
  () => <Title titleColor="bam" label="Bam" />,
  {
    notes: 'A very simple component',
  },
);
storiesOf('Title', module).add(
  'Title - custom with icon',
  () => <Title label="Test" icon="object" />,
  { notes: 'A very simple component' },
);
storiesOf('Title', module).add(
  'Title - custom blue title with icon',
  () => <Title label="Test" icon="puzzle" titleColor="blue" />,
  { notes: 'A very simple component' },
);
