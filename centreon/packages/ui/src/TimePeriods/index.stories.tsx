import React from 'react';

import { ComponentStory } from '@storybook/react';
import dayjs from 'dayjs';

import { dateFormat } from './models';

import AwesomeTimePeriod from '.';

export default {
  Component: AwesomeTimePeriod,
  argTypes: {
    extraTimePeriods: { control: 'object' }
  },
  title: 'TimePeriod'
};

const Template: ComponentStory<typeof AwesomeTimePeriod> = (args) => {
  const { extraTimePeriods } = args;

  return <AwesomeTimePeriod extraTimePeriods={extraTimePeriods} />;
};

export const Playground = Template.bind({});

Playground.args = {
  extraTimePeriods: [
    {
      dateTimeFormat: dateFormat,
      getStart: (): Date => dayjs(Date.now()).subtract(2, 'day').toDate(),
      id: 'last_2_days',
      largeName: 'last 2 days',
      name: '2 days',
      timelineEventsLimit: 100
    }
  ]
};
