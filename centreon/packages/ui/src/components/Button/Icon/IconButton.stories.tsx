import { Meta, StoryObj } from '@storybook/react';

import { Add as AddIcon } from '@mui/icons-material';

import { IconButton } from './IconButton';

const meta: Meta<typeof IconButton> = {
  component: IconButton
};

export default meta;
type Story = StoryObj<typeof IconButton>;

export const Default: Story = {
  args: {
    icon: <AddIcon />
  }
};
