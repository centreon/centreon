import { Meta, StoryObj } from '@storybook/react';

import dataLastWeek from '../mockedData/lastWeek.json';

import { SingleBar } from '.';

const meta: Meta<typeof SingleBar> = {
  component: SingleBar
};

export default meta;
type Story = StoryObj<typeof SingleBar>;

const Template = (props): JSX.Element => (
  <div style={{ height: '500px', width: '500px' }}>
    <SingleBar {...props} />
  </div>
);

const SmallTemplate = (props): JSX.Element => (
  <div style={{ height: '130px', width: '130px' }}>
    <SingleBar {...props} />
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
          value: 0.5
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

export const disabled: Story = {
  args: {
    data: dataLastWeek,
    thresholds: {
      critical: [
        {
          label: 'Critical',
          value: 0.35
        }
      ],
      enabled: false,
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

export const criticalLowerThanWarning: Story = {
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

export const rangeThresholds: Story = {
  args: {
    data: dataLastWeek,
    thresholds: {
      critical: [
        {
          label: 'Critical 1',
          value: 0.5
        },
        {
          label: 'Critical 2',
          value: 0.6
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'Warning 1',
          value: 0.13
        },
        {
          label: 'Warning 2',
          value: 0.3
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
      enabled: false,
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

export const Small: Story = {
  args: {
    data: dataLastWeek,
    size: 'small',
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

export const smallDisplay: Story = {
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
  render: SmallTemplate
};
