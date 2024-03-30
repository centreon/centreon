import { Meta, StoryObj } from '@storybook/react';

import Zoom, { ZoomProps } from './Zoom';
import { ChildrenProps } from './models';

const meta: Meta<typeof Zoom> = {
  argTypes: {
    minimapPosition: {
      control: 'select',
      options: ['top-left', 'top-right', 'bottom-left', 'bottom-right']
    },
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

const Content = ({ contentClientRect }: ChildrenProps): JSX.Element => {
  const contentRect = {
    height: contentClientRect?.height || 1,
    width: contentClientRect?.width || 1
  };
  const isPortrait = contentRect.height > contentRect.width;
  const sizes = isPortrait ? ['width', 'height'] : ['height', 'width'];
  const sizeScale = contentRect[sizes[0]] / contentRect[sizes[1]];

  const lengthToUse = isPortrait
    ? contentRect[sizes[1]] - contentRect[sizes[0]]
    : contentRect[sizes[0]];

  return (
    <g
      style={{
        transform: `translate(-${isPortrait ? 0 : contentRect.width * (sizeScale / 4)}px, -${lengthToUse * (isPortrait ? sizeScale + 0.08 : sizeScale / 2)}px)`
      }}
    >
      <g style={{ transform: 'translate(300px, 150px)' }}>
        <circle fill="blue" r={50} stroke="black" />
      </g>
      <g style={{ transform: 'translate(600px, 400px)' }}>
        <circle fill="green" r={70} />
      </g>
      <g style={{ transform: 'translate(2400px, 2400px)' }}>
        <circle fill="red" r={70} />
      </g>
    </g>
  );
};

const Template = ({ children, ...args }: ZoomProps): JSX.Element => (
  <div style={{ height: '400px', width: '100%' }}>
    <Zoom {...args}>{children}</Zoom>
  </div>
);

export const WithoutMinimap: Story = {
  args: {
    children: Content
  },
  render: Template
};

export const WithMinimap: Story = {
  args: {
    children: Content,
    showMinimap: true
  },
  render: Template
};

export const WithMinimapPosition: Story = {
  args: {
    children: Content,
    minimapPosition: 'bottom-right',
    showMinimap: true
  },
  render: Template
};
