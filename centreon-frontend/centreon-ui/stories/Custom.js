/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { CustomButton } from '../src';

storiesOf('Custom tags', module).add(
  'Custom - button',
  () => {
    return (
      <React.Fragment>
        <CustomButton label="Warning" color="orange" />
        <CustomButton label="Critical" color="red" />
        <CustomButton label="OK" color="green" />
        <CustomButton label="Unknown" color="gray" />
      </React.Fragment>
    );
  },
  { notes: 'A very simple component' },
);
