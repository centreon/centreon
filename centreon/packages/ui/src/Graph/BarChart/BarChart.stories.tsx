import { Meta, StoryObj } from '@storybook/react';
import dayjs from 'dayjs';

import { LineChartData } from '../common/models';
import dataPingService from '../mockedData/pingService.json';
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
