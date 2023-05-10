import { Meta, StoryObj } from '@storybook/react';
import { Button } from './Button';
import { Add as AddIcon } from '@mui/icons-material';

const meta: Meta<typeof Button> = {
  component: Button,
}

export default meta;
type Story = StoryObj<typeof Button>

export const Default: Story = {
  args: {
    children: 'Label',
  }
}

export const WithIcon: Story = {
  args: {
    ...Default.args,
    iconVariant: 'start',
    icon: <AddIcon />, // TODO Icon component
  }
}