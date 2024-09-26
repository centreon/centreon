import { Meta, StoryObj } from '@storybook/react';

import Timeline from './ResponsiveTimeline';

const data = [
  {
    start: new Date('2024-09-25T21:00:42Z'),
    end: new Date('2024-09-25T21:15:00Z'),
    color: 'gray'
  },
  {
    start: new Date('2024-09-25T21:15:00Z'),
    end: new Date('2024-09-25T21:54:00Z'),
    color: 'green'
  },
  {
    start: new Date('2024-09-25T21:54:00Z'),
    end: new Date('2024-09-25T22:30:00Z'),
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
    <div style={{ color, padding : "10px" }}>
      <div>{duration}</div>
      <div>{`${start} - ${end}`}</div>
    </div>
  );
};

const Template = (args): JSX.Element => {
  return <Timeline height={120} width={600} {...args} />;
};

export const Vertical: Story = {
  args: {
    data,
    startDate,
    endDate,
    TooltipContent
  },
  render: Template
};
