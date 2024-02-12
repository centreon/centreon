import { Meta, StoryObj } from '@storybook/react';

import { ArcType } from './models';

import { PieChart } from '.';

const data = [
  { color: '#88B922', label: 'Ok', value: 148 },
  { color: '#999999', label: 'Unknown', value: 13 },
  { color: '#F7931A', label: 'Warning', value: 16 },
  { color: '#FF6666', label: 'Down', value: 62 }
];

const dataWithBigNumbers = [
  { color: '#88B922', label: 'Ok', value: 260000 },
  { color: '#999999', label: 'Unknown', value: 1010900 },
  { color: '#F7931A', label: 'Warning', value: 63114 },
  { color: '#FF6666', label: 'Down', value: 122222 }
];

const meta: Meta<typeof PieChart> = {
  component: PieChart
};

export default meta;
type Story = StoryObj<typeof PieChart>;

const Template = (args): JSX.Element => {
  return (
    <div style={{ width: '20%' }}>
      <PieChart {...args} />
    </div>
  );
};

export const Pie: Story = {
  args: {
    data,
    title: 'hosts'
  },
  render: Template
};

export const Donut: Story = {
  args: {
    data,
    title: 'hosts',
    variant: 'Donut'
  },
  render: Template
};

export const WithPencentage: Story = {
  args: {
    data,
    title: 'hosts',
    unit: 'Percentage',
    variant: 'Donut'
  },
  render: Template
};

export const WithBigNumbers: Story = {
  args: {
    data: dataWithBigNumbers,
    title: 'hosts',
    unit: 'Number',
    variant: 'Donut'
  },
  render: Template
};

export const WithoutLegend: Story = {
  args: {
    data,
    displayLegend: false,
    title: 'hosts',
    variant: 'Donut'
  },
  render: Template
};

export const DonutWithoutTitle: Story = {
  args: {
    data,
    variant: 'Donut'
  },
  render: Template
};

export const PieWithoutTitle: Story = {
  args: {
    data
  },
  render: Template
};

export const DonutWithDisplayedValues: Story = {
  args: {
    data,
    displayValues: true,
    variant: 'Donut'
  },
  render: Template
};

export const PieWithDisplayedValues: Story = {
  args: {
    data,
    displayValues: true
  },
  render: Template
};

const Tooltip = ({ label, color, value }: ArcType): JSX.Element => {
  return (
    <div style={{ color }}>
      {label} : {value}
    </div>
  );
};

export const PieWithTooltip: Story = {
  args: {
    Tooltip,
    data,
    displayValues: true
  },
  render: Template
};

export const DonutWithTooltip: Story = {
  args: {
    Tooltip,
    data,
    displayValues: true,
    variant: 'Donut'
  },
  render: Template
};
