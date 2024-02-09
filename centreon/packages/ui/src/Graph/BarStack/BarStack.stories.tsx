import { Meta, StoryObj } from '@storybook/react';

import { BarType } from './models';

import { BarStack } from '.';

const data = [
  { color: '#88B922', label: 'Ok', value: 148 },
  { color: '#999999', label: 'Unknown', value: 13 },
  { color: '#F7931A', label: 'Warning', value: 16 },
  { color: '#FF6666', label: 'Down', value: 62 }
];

const meta: Meta<typeof BarStack> = {
  component: BarStack
};

export default meta;
type Story = StoryObj<typeof BarStack>;

const Tooltip = ({ label, color, value }: BarType): JSX.Element => {
  return (
    <div style={{ color }}>
      {label} : {value}
    </div>
  );
};

const Template = (args): JSX.Element => {
  return (
    <div style={{ height: '300px', width: '60px' }}>
      <BarStack {...args} />
    </div>
  );
};

export const Normal: Story = {
  args: { data, title: 'hosts' },
  render: Template
};

export const WithoutTitle: Story = {
  args: { data },
  render: Template
};

export const WithPencentage: Story = {
  args: { data, title: 'hosts', unit: 'Percentage' },
  render: Template
};

export const WidthVerticalLegend: Story = {
  args: {
    data,
    legendConfiguration: { direction: 'column' },
    title: 'hosts'
  },
  render: Template
};

export const WithoutLegend: Story = {
  args: { data, displayLegend: false, title: 'hosts' },

  render: Template
};

export const withithDisplayedValues: Story = {
  args: { data, displayValues: true, title: 'hosts' },
  render: Template
};

export const WidthTooltip: Story = {
  args: { Tooltip, data, title: 'hosts' },
  render: Template
};
