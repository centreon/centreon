import { Meta, StoryObj } from '@storybook/react';

import Zoom, { ZoomProps } from './Zoom';

const meta: Meta<typeof Zoom> = {
  argTypes: {
    scaleMax: {
      control: { max: 16, min: 0.4, step: 0.2, type: 'range' }
    },
    scaleMin: {
      control: { max: 16, min: 0.4, step: 0.2, type: 'range' }
    },
    showMinimap: {
      control: 'boolean'
    }
  },
  component: Zoom
};

export default meta;
type Story = StoryObj<typeof Zoom>;

const content = (
  <g style={{ transform: 'translate(300px, 150px)' }}>
    <circle fill="blue" r={50} stroke="black" />
  </g>
);

const Template = ({ children, ...args }: ZoomProps): JSX.Element => (
  <div style={{ height: '400px', width: '100%' }}>
    <Zoom {...args}>{children}</Zoom>
  </div>
);

const labels = {
  clear: 'Clear',
  zoomIn: '+',
  zoomOut: '-'
};

export const Default: Story = {
  args: {
    children: content,
    labels,
    showMinimap: true
  },
  render: Template
};

export const WithoutMinimap: Story = {
  args: {
    children: content,
    labels
  },
  render: Template
};
