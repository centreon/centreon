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
  return <AwesomeTimePeriod {...args} />;
};

export const Playground = Template.bind({});

Playground.args = {
  extraTimePeriods: [
    {
      dateTimeFormat: dateFormat,
      getStart: (): Date => dayjs(Date.now()).subtract(29, 'day').toDate(),
      id: 'last_29_days',
      largeName: 'last 29 days',
      name: '29 days',
      timelineEventsLimit: 100
    }
  ]
};
Playground.argTypes = {
  disabled: {
    control: 'boolean',
    defaultValue: false,
    description: 'If true, the component is disabled.'
  },
  extraTimePeriods: {
    control: 'object',
    description: 'soon',
    table: {
      type: { detail: 'extra selected time periods', summary: 'array' }
    }
  },
  getIsError: {
    description:
      'Callback fired when The end date is smaller or equal the start date',
    table: {
      category: 'Events',
      type: { detail: '(value:boolean)=>void', summary: 'function' }
    }
  },
  getStartEndParameters: {
    description: 'Callback fired when the the user select or pick a date',
    table: {
      category: 'Events',
      type: {
        detail: '({start:isoString,end:isoString})=>void',
        summary: 'function'
      }
    }
  }
};
