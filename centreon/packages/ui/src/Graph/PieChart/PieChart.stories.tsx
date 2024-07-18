import { Meta, StoryObj } from '@storybook/react';

import { ArcType } from './models';
import ResponsivePie from './ResponsivePie';

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

const meta: Meta<typeof ResponsivePie> = {
  component: ResponsivePie,
  parameters: {
    chromatic: {
      delay: 1000
    }
  }
};

export default meta;
type Story = StoryObj<typeof ResponsivePie>;

const Template = (args): JSX.Element => {
  return <ResponsivePie height={350} width={350} {...args} />;
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
    variant: 'donut'
  },
  render: Template
};

export const WithPencentage: Story = {
  args: {
    data,
    title: 'hosts',
    unit: 'percentage',
    variant: 'donut'
  },
  render: Template
};

export const WithBigNumbers: Story = {
  args: {
    data: dataWithBigNumbers,
    title: 'hosts',
    unit: 'number',
    variant: 'donut'
  },
  render: Template
};

export const WithoutLegend: Story = {
  args: {
    data,
    displayLegend: false,
    title: 'hosts',
    variant: 'donut'
  },
  render: Template
};

export const DonutWithoutTitle: Story = {
  args: {
    data,
    variant: 'donut'
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
    variant: 'donut'
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

const TooltipContent = ({ label, color, value }: ArcType): JSX.Element => {
  return (
    <div style={{ color }}>
      {label} : {value}
    </div>
  );
};

export const PieWithTooltip: Story = {
  args: {
    TooltipContent,
    data,
    displayValues: true,
    unit: 'percentage'
  },
  render: Template
};

export const DonutWithTooltip: Story = {
  args: {
    TooltipContent,
    data,
    displayValues: true,
    variant: 'donut'
  },
  render: Template
};

const TemplateForSmallDimensions = (args): JSX.Element => {
  return <ResponsivePie height={130} width={130} {...args} />;
};

const SmallTemplate = (args): JSX.Element => {
  return <ResponsivePie height={100} width={100} {...args} />;
};

export const PieWithSmallDimensions: Story = {
  args: {
    data,
    displayLegend: false
  },
  render: TemplateForSmallDimensions
};

export const DonutWithSmallDimensions: Story = {
  args: {
    data,
    displayLegend: false,
    title: 'hosts',
    variant: 'donut'
  },
  render: TemplateForSmallDimensions
};

const dataWidthOneNoZeroValue = [
  { color: '#88B922', label: 'Ok', value: 13 },
  { color: '#999999', label: 'Unknown', value: 0 },
  { color: '#F7931A', label: 'Warning', value: 0 },
  { color: '#FF6666', label: 'Down', value: 0 }
];

export const PieWithOneNoZeroValue: Story = {
  args: {
    data: dataWidthOneNoZeroValue,
    displayLegend: false,
    title: 'hosts',
    variant: 'pie'
  },
  render: Template
};

export const donutWithOneNoZeroValue: Story = {
  args: {
    data: dataWidthOneNoZeroValue,
    displayLegend: false,
    title: 'hosts',
    variant: 'donut'
  },
  render: Template
};

export const smallDisplay: Story = {
  args: {
    data,
    displayLegend: false,
    title: 'hosts',
    variant: 'donut'
  },
  render: SmallTemplate
};
