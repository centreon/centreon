import { Meta, StoryObj } from '@storybook/react';

import Timeline from './Timeline';

const data = [
  {
    start: '2024-09-25T21:00:42Z',
    end: '2024-09-25T21:15:00Z',
    color: 'gray'
  },
  {
    start: '2024-09-25T21:15:00Z',
    end: '2024-09-25T21:54:00Z',
    color: 'green'
  },
  {
    start: '2024-09-25T21:54:00Z',
    end: '2024-09-25T22:30:00Z',
    color: 'red'
  }
];

const startDate = '2024-09-25T21:00:42Z';
const endDate = '2024-09-25T22:30:00Z';

const meta: Meta<typeof Timeline> = {
  component: Timeline,
  parameters: {
    chromatic: {
      delay: 1000
    }
  }
};

export default meta;
type Story = StoryObj<typeof Timeline>;

const TooltipContent = ({ start, end, duration, color }): JSX.Element => {
  return (
    <div style={{ color, padding: '10px' }}>
      <div>{duration}</div>
      <div>{`${start} - ${end}`}</div>
    </div>
  );
};

const Template = (args): JSX.Element => {
  return (
    <div
      style={{
        width: '100%',
        height: '80vh',
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center'
      }}
    >
      <div style={{ width: '700px', height: '100px' }}>
        <Timeline {...args} />
      </div>
    </div>
  );
};

export const Normal: Story = {
  args: {
    data,
    startDate,
    endDate,
    TooltipContent
  },
  render: Template
};
