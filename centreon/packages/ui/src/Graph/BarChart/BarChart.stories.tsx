import { Meta, StoryObj } from '@storybook/react';
import dayjs from 'dayjs';

import { LineChartData } from '../common/models';
import dataPingService from '../mockedData/pingService.json';
import dataPingServiceMixedStacked from '../mockedData/pingServiceMixedStacked.json';
import dataPingServiceStacked from '../mockedData/pingServiceStacked.json';

import BarChart from './BarChart';

const meta: Meta<typeof BarChart> = {
  component: BarChart
};
export default meta;

type Story = StoryObj<typeof BarChart>;

const defaultStart = new Date(
  dayjs(Date.now()).subtract(24, 'hour').toDate().getTime()
).toISOString();

const defaultEnd = new Date(Date.now()).toISOString();

const defaultArgs = {
  end: defaultEnd,
  height: 500,
  loading: false,
  start: defaultStart
};

const Template = (args): JSX.Element => (
  <BarChart data={dataPingService as unknown as LineChartData} {...args} />
);

export const Default: Story = {
  args: defaultArgs,
  render: Template
};

export const withCenteredZero: Story = {
  args: {
    ...defaultArgs,
    axis: {
      isCenteredZero: true
    }
  },
  render: Template
};

export const vertical: Story = {
  args: {
    ...defaultArgs,
    height: 800,
    orientation: 'vertical'
  },
  render: Template
};

export const verticalCenteredZero: Story = {
  args: {
    ...defaultArgs,
    axis: {
      isCenteredZero: true
    },
    height: 800,
    orientation: 'vertical'
  },
  render: Template
};

export const stacked: Story = {
  args: {
    ...defaultArgs,
    data: dataPingServiceStacked
  },
  render: Template
};

export const stackedVertical: Story = {
  args: {
    ...defaultArgs,
    data: dataPingServiceStacked,
    height: 800,
    orientation: 'vertical'
  },
  render: Template
};

export const stackedCenteredZero: Story = {
  args: {
    ...defaultArgs,
    axis: {
      isCenteredZero: true
    },
    data: dataPingServiceStacked
  },
  render: Template
};

export const stackedVerticalCenteredZero: Story = {
  args: {
    ...defaultArgs,
    axis: {
      isCenteredZero: true
    },
    data: dataPingServiceStacked,
    height: 800,
    orientation: 'vertical'
  },
  render: Template
};

export const thresholds: Story = {
  args: {
    ...defaultArgs,
    thresholdUnit: 'ms',
    thresholds: {
      critical: [
        {
          label: 'critical 1',
          value: 0.1
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'warning 1',
          value: 0.05
        }
      ]
    }
  },
  render: Template
};

export const thresholdsVertical: Story = {
  args: {
    ...defaultArgs,
    axis: {
      isCenteredZero: true
    },
    orientation: 'vertical',
    thresholdUnit: 'ms',
    thresholds: {
      critical: [
        {
          label: 'critical 1',
          value: 0.1
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'warning 1',
          value: 0.05
        }
      ]
    }
  },
  render: Template
};

export const thresholdStacked: Story = {
  args: {
    ...defaultArgs,
    data: dataPingServiceStacked,
    thresholdUnit: 'ms',
    thresholds: {
      critical: [
        {
          label: 'critical 1',
          value: 0.1
        }
      ],
      enabled: true,
      warning: [
        {
          label: 'warning 1',
          value: 0.05
        }
      ]
    }
  },
  render: Template
};

export const customBarStyle: Story = {
  args: {
    ...defaultArgs,
    barStyle: {
      opacity: 0.5,
      radius: 0.5
    }
  },
  render: Template
};

export const mixedStacked: Story = {
  args: {
    ...defaultArgs,
    data: dataPingServiceMixedStacked
  },
  render: Template
};

export const mixedStackedVertical: Story = {
  args: {
    ...defaultArgs,
    data: dataPingServiceMixedStacked,
    orientation: 'vertical'
  },
  render: Template
};
