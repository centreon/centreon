import { Meta, StoryObj } from '@storybook/react';

import dataLastWeek from '../LineChart/mockedData/lastWeek.json';

import { Text } from '.';

const meta: Meta<typeof Text> = {
  component: Text
};

export default meta;
type Story = StoryObj<typeof Text>;

const Template = (props): JSX.Element => (
  <div style={{ height: '500px', width: '500px' }}>
    <Text {...props} />
  </div>
);

export const success: Story = {
  args: {
    data: dataLastWeek,
    labels: {
      critical: 'Critical',
      warning: 'Warning'
    },
    thresholds: [0.5, 1.5]
  },
  render: Template
};

export const warning: Story = {
  args: {
    data: dataLastWeek,
    labels: {
      critical: 'Critical',
      warning: 'Warning'
    },
    thresholds: [0.2, 0.5]
  },
  render: Template
};

export const critical: Story = {
  args: {
    data: dataLastWeek,
    labels: {
      critical: 'Critical',
      warning: 'Warning'
    },
    thresholds: [0.13, 0.35]
  },
  render: Template
};
