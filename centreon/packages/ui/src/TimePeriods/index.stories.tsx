import { Meta, StoryObj } from '@storybook/react';
import dayjs from 'dayjs';

import { dateFormat } from './models';

import AwesomeTimePeriod from '.';

const meta: Meta<typeof AwesomeTimePeriod> = {
  component: AwesomeTimePeriod,
  tags: ['autodocs']
};

export default meta;

type Story = StoryObj<typeof AwesomeTimePeriod>;

const Template: Story = {
  render: (args) => <AwesomeTimePeriod {...args} />
};

const args = {
  extraTimePeriods: [
    {
      dateTimeFormat: dateFormat,
      getStart: (): Date => dayjs(Date.now()).subtract(29, 'day').toDate(),
      id: 'last_29_days',
      largeName: 'last 29 days',
      name: '29 days'
    }
  ]
};

const argTypes = {
  disabled: {
    control: 'boolean',
    description: 'If true, the component is disabled.',
    table: {
      defaultValue: { summary: false },
      type: { summary: 'boolean' }
    }
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
      'Callback fired when The end date is smaller or equal to the start date',
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

export const Playground: Story = {
  ...Template,
  argTypes,
  args
};
