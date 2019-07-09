/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { InfoTooltip } from '../src';

storiesOf('Tooltip', module).add(
  'Tooltip',
  () => (
    <InfoTooltip
      iconColor="gray"
      tooltipText="This is the an example of tooltip"
    />
  ),
  {
    notes: 'A very simple component',
  },
);

storiesOf('Tooltip', module).add(
  'Tooltip - with icon text',
  () => (
    <InfoTooltip
      iconColor="gray"
      tooltipText="This is the an example of tooltip"
      iconText="Tooltip with text"
    />
  ),
  {
    notes: 'A very simple component',
  },
);
