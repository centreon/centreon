import { Meta, StoryObj } from '@storybook/react';

import dataLastWeek from '../mockedData/lastWeek.json';

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

const SmallTemplate = (props): JSX.Element => (
  <div style={{ height: '100px', width: '200px' }}>
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
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 1.5
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'Warning',
          value: 0.5
        }
      ]
    }
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
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 1.5
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'Warning',
          value: 0.4
        }
      ]
    }
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
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 0.3
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'Warning',
          value: 0.2
        }
      ]
    }
  },
  render: Template
};

export const rawValue: Story = {
  args: {
    data: dataLastWeek,
    displayAsRaw: true,
    labels: {
      critical: 'Critical',
      warning: 'Warning'
    },
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 1.5
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'Warning',
          value: 0.5
        }
      ]
    }
  },
  render: Template
};

export const smallDisplay: Story = {
  args: {
    data: dataLastWeek,
    labels: {
      critical: 'Critical',
      warning: 'Warning'
    },
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 1.5
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'Warning',
          value: 0.5
        }
      ]
    }
  },
  render: SmallTemplate
};
