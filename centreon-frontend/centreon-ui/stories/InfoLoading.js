/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { InfoLoading } from '../src';

storiesOf('Info', module).add(
  'Info - loading',
  () => (
    <React.Fragment>
      <InfoLoading
        label="Loading job may take some while"
        infoType="bordered"
        color="orange"
        iconActionType="clock"
        iconColor="orange"
      />
      <InfoLoading
        label="Loading job may take some while"
        infoType="bordered"
        color="orange"
        iconActionType="warning"
        iconColor="orange"
      />
    </React.Fragment>
  ),
  { notes: 'A very simple component' },
);
