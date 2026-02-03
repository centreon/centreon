import { Typography } from '@mui/material';

import type { Meta, StoryObj } from '@storybook/react';

import Timeline from './Timeline';

const data = [
  {
    color: 'gray',
    end: '2024-09-25T21:15:00+01:00',
    start: '2024-09-25T21:00:42+01:00'
  },
  {
    color: 'green',
    end: '2024-09-25T21:54:00+01:00',
    start: '2024-09-25T21:15:00+01:00'
  },
  {
    color: 'red',
    end: '2024-09-25T22:30:00+01:00',
    start: '2024-09-25T21:54:00+01:00'
  }
];

const startDate = '2024-09-25T21:00:42+01:00';
const endDate = '2024-09-25T22:30:00+01:00';

const Template = (args): JSX.Element => {
  return (
    <div style={{ height: '100px', width: '700px' }}>
      <Timeline {...args} />
    </div>
  );
};

const meta: Meta<typeof Timeline> = {
  component: Timeline,
  parameters: {
    chromatic: {
      delay: 1000
    }
  },
  render: Template
};

export default meta;
type Story = StoryObj<typeof Timeline>;

export const Normal: Story = {
  args: {
    data,
    endDate,
    startDate
  }
};

export const WithoutData: Story = {
  args: {
    data: [],
    endDate,
    startDate
  }
};

export const WithSmallerTimeRangeThanData: Story = {
  args: {
    data,
    endDate: '2024-09-25T22:00:00+01:00',
    startDate
  }
};

export const WithCustomTooltip: Story = {
  args: {
    data,
    endDate,
    startDate,
    TooltipContent: ({ duration, color }) => (
      <div style={{ display: 'flex', flexDirection: 'row', gap: '8px' }}>
        <div
          style={{
            backgroundColor: color,
            borderRadius: '4px',
            height: '20px',
            width: '20px'
          }}
        />
        <Typography>{duration}</Typography>
      </div>
    )
  }
};
