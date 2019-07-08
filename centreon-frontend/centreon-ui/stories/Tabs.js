/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { Tabs, Tab } from '../src';

storiesOf('Tabs', module).add(
  'Tabs - custom',
  () => (
    <Tabs>
      <Tab label="Configuration">Lorem Ipsum dolor sit amet Configuration</Tab>
      <Tab label="Reporting">Lorem Ipsum dolor sit amet Reporting</Tab>
      <Tab label="Escalation">Lorem Ipsum dolor sit amet Escalation</Tab>
      <Tab label="Event Handler">Lorem Ipsum dolor sit amet Event Handler</Tab>
    </Tabs>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Tabs', module).add(
  'Tabs - custom with errors',
  () => (
    <Tabs error="The form has errors">
      <Tab label="Configuration">Lorem Ipsum dolor sit amet Configuration</Tab>
      <Tab error="The form has errors" label="Indicators">
        Lorem Ipsum dolor sit amet Indicators
      </Tab>
      <Tab label="Reporting">Lorem Ipsum dolor sit amet Reporting</Tab>
      <Tab label="Escalation">Lorem Ipsum dolor sit amet Escalation</Tab>
      <Tab label="Event Handler">Lorem Ipsum dolor sit amet Event Handler</Tab>
    </Tabs>
  ),
  { notes: 'A very simple component' },
);
