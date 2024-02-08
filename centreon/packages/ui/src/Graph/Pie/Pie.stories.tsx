import { Meta, StoryObj } from '@storybook/react';

import { Pie as PieComponent } from '.';

const data = [
  { color: '#88B922', label: 'Ok', value: 148 },
  { color: '#999999', label: 'Unknown', value: 13 },
  { color: '#F7931A', label: 'Warning', value: 16 },
  { color: '#FF6666', label: 'Down', value: 62 }
];

const dataWithLargeNumber = [
  { color: '#88B922', label: 'Ok', value: 260000 },
  { color: '#999999', label: 'Unknown', value: 1010900 },
  { color: '#F7931A', label: 'Warning', value: 63114 },
  { color: '#FF6666', label: 'Down', value: 122222 }
];

const meta: Meta<typeof PieComponent> = {
  component: PieComponent
};

export default meta;
type Story = StoryObj<typeof PieComponent>;

const Template = (args): JSX.Element => {
  return (
    <div style={{ width: '250px' }}>
      <PieComponent {...args} />
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

export const WithLargeNumber: Story = {
  args: {
    data: dataWithLargeNumber,
    title: 'hosts',
    unit: 'Number',
    variant: 'Donut'
  },
  render: Template
};

export const WithoutLegend: Story = {
  args: {
    data,
    legend: false,
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
