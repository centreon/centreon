import { Meta, StoryObj } from '@storybook/react';

import { Typography } from '@mui/material';
import Timeline from './Timeline';

const data = [
  {
    start: '2024-09-25T21:00:42+01:00',
    end: '2024-09-25T21:15:00+01:00',
    color: 'gray'
  },
  {
    start: '2024-09-25T21:15:00+01:00',
    end: '2024-09-25T21:54:00+01:00',
    color: 'green'
  },
  {
    start: '2024-09-25T21:54:00+01:00',
    end: '2024-09-25T22:30:00+01:00',
    color: 'red'
  }
];

const startDate = '2024-09-25T21:00:42+01:00';
const endDate = '2024-09-25T22:30:00+01:00';

const Template = (args): JSX.Element => {
  return (
    <div style={{ width: '700px', height: '100px' }}>
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
    startDate,
    endDate
  }
};

export const WithoutData: Story = {
  args: {
    data: [],
    startDate,
    endDate
  }
};

export const WithSmallerTimeRangeThanData: Story = {
  args: {
    data,
    startDate,
    endDate: '2024-09-25T22:00:00+01:00'
  }
};

export const WithCustomTooltip: Story = {
  args: {
    data,
    startDate,
    endDate,
    TooltipContent: ({ duration, color }) => (
      <div style={{ display: 'flex', flexDirection: 'row', gap: '8px' }}>
        <div
          style={{
            backgroundColor: color,
            width: '20px',
            height: '20px',
            borderRadius: '4px'
          }}
        />
        <Typography>{duration}</Typography>
      </div>
    )
  }
};
