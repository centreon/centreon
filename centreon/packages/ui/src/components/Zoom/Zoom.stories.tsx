import { Meta, StoryObj } from '@storybook/react';

import Zoom, { ZoomProps } from './Zoom';

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

const Content = (): JSX.Element => {
  return (
    <g style={{ transform: 'translate(-150px, -100px)' }}>
      <g style={{ transform: 'translate(300px, 150px)' }}>
        <circle fill="blue" r={50} stroke="black" />
      </g>
      <g style={{ transform: 'translate(600px, 400px)' }}>
        <circle fill="green" r={70} />
      </g>
      <g style={{ transform: 'translate(2400px, 1400px)' }}>
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

const TemplateResponsive = ({
  redCircleXPosition,
  redCircleYPosition,
  ...args
}: ZoomProps): JSX.Element => (
  <div style={{ height: '90vh', width: '100%' }}>
    <Zoom {...args}>
      {() => (
        <g>
          <g style={{ transform: 'translate(500px, 100px)' }}>
            <circle fill="blue" r={50} stroke="black" />
          </g>
          <g style={{ transform: 'translate(600px, 400px)' }}>
            <circle fill="green" r={70} />
          </g>
          <g
            style={{
              transform: `translate(${redCircleXPosition || 100}px, ${redCircleYPosition || 100}px)`
            }}
          >
            <circle fill="red" r={70} />
          </g>
        </g>
      )}
    </Zoom>
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

export const Playground: Story = {
  argTypes: {
    redCircleXPosition: {
      control: { max: 2600, min: 400, step: 10, type: 'range' }
    },
    redCircleYPosition: {
      control: { max: 2600, min: 400, step: 10, type: 'range' }
    }
  },
  args: {
    children: Content,
    showMinimap: true
  },
  render: TemplateResponsive
};
