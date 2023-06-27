import { Meta, StoryObj } from '@storybook/react';

import { Info as InfoIcon } from '@mui/icons-material';

import { Tooltip } from './Tooltip';

const meta: Meta<typeof Tooltip> = {
  argTypes: {
    position: {
      control: {
        options: ['top', 'bottom', 'left', 'right']
      }
    }
  },
  component: Tooltip,
  parameters: {
    layout: 'centered'
  }
};

export default meta;
type Story = StoryObj<typeof Tooltip>;

export const Default: Story = {
  args: {
    children: <InfoIcon />,
    label: 'Tooltip content'
  }
};

export const WrappedContent: Story = {
  args: {
    children: <InfoIcon />,
    followCursor: false,
    hasCaret: true,
    isOpen: true,
    label:
      'Qui deserunt pariatur quis. Duis nisi velit culpa labore ipsum reprehenderit sunt laborum anim sint quis magna consequat amet. Voluptate tempor nostrud eiusmod enim qui reprehenderit.',
    position: 'right'
  }
};
