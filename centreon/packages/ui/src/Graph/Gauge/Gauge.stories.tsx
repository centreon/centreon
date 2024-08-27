import { Meta, StoryObj } from '@storybook/react';

import dataLastWeek from '../mockedData/lastWeek.json';

import { Gauge } from './Gauge';

const meta: Meta<typeof Gauge> = {
  component: Gauge
};

export default meta;
type Story = StoryObj<typeof Gauge>;

const Template = (props): JSX.Element => (
  <div style={{ display: 'flex', flexDirection: 'row' }}>
    <div style={{ height: '500px', width: '500px' }}>
      <Gauge {...props} />
    </div>
    <div style={{ height: '100px', width: '120px' }}>
      <Gauge {...props} />
    </div>
    <div style={{ height: '300px', width: '200px' }}>
      <Gauge {...props} />
    </div>
  </div>
);

export const success: Story = {
  args: {
    data: dataLastWeek,
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 0.6
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
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 0.6
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

export const critical: Story = {
  args: {
    data: dataLastWeek,
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 0.35
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'Warning',
          value: 0.13
        }
      ]
    }
  },
  render: Template
};

export const withCriticalLowerThanWarning: Story = {
  args: {
    data: dataLastWeek,
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 0.13
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

export const withRangeThresholds: Story = {
  args: {
    data: dataLastWeek,
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 0.55
        },
        {
          label: 'Critical',
          value: 0.65
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'Warning',
          value: 0.13
        },
        {
          label: 'Warning',
          value: 0.5
        }
      ]
    }
  },
  render: Template
};

export const RawValue: Story = {
  args: {
    data: dataLastWeek,
    displayAsRaw: true,
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 0.6
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
