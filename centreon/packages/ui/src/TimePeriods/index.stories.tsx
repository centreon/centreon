import { Meta, StoryObj } from '@storybook/react';
import dayjs from 'dayjs';

import Switch from '@mui/material/Switch';

import SimpleCustomTimePeriod from './CustomTimePeriod/SimpleCustomTimePeriod';
import { dateFormat } from './models';

import TimePeriod from '.';

const meta: Meta<typeof TimePeriod> = {
  component: TimePeriod,
  parameters: {
    chromatic: { disableSnapshot: true }
  },
  tags: ['autodocs']
};

export default meta;

type Story = StoryObj<typeof TimePeriod>;

type StorySimpleTimePeriod = StoryObj<typeof SimpleCustomTimePeriod>;

const Template: Story = {
  render: (args) => <TimePeriod {...args} />
};

const TemplateWithSimpleTimePeriod: StorySimpleTimePeriod = {
  render: (args) => <SimpleCustomTimePeriod {...args} />
};

const TemplateWithExternalComponent: Story = {
  render: (args) => (
    <TimePeriod {...args} renderExternalComponent={<Switch />} />
  )
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
      defaultValue: { summary: '[]' },
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
const args = {
  extraTimePeriods: [
    {
      dateTimeFormat: dateFormat,
      getStart: (): Date => dayjs(Date.now()).subtract(29, 'day').toDate(),
      id: 'last_29_days',
      largeName: 'last 29 days',
      name: '29 days'
    },
    {
      dateTimeFormat: dateFormat,
      getStart: (): Date => dayjs(Date.now()).subtract(5, 'day').toDate(),
      id: 'last_5_days',
      largeName: 'last 5 days',
      name: '5 days'
    }
  ]
};

export const BasicTimePeriod: Story = {
  ...Template,
  argTypes
};

export const WithExtraTimePeriods: Story = {
  ...Template,
  argTypes,
  args
};

export const WithExternalComponent: Story = {
  ...TemplateWithExternalComponent,
  argTypes
};

export const SimpleTimePeriod: StorySimpleTimePeriod = {
  ...TemplateWithSimpleTimePeriod,
  args: {
    endDate: dayjs(Date.now()).toDate(),
    startDate: dayjs(Date.now()).subtract(29, 'day').toDate()
  }
};
