import '../../ThemeProvider/tailwindcss.css';

import { Add as AddIcon } from '@mui/icons-material';

import type { Meta, StoryObj } from '@storybook/react';

import { Button } from './Button';

const meta: Meta<typeof Button> = {
  component: Button
};

export default meta;
type Story = StoryObj<typeof Button>;

export const Default: Story = {
  args: {
    'aria-label': 'button',
    children: 'Label'
  }
};

export const WithIcon: Story = {
  args: {
    ...Default.args,
    icon: <AddIcon />,
    iconVariant: 'start'
  }
};

export const AsDanger: Story = {
  args: {
    ...Default.args,
    isDanger: true
  }
};

export const small: Story = {
  args: {
    ...Default.args,
    size: 'small'
  }
};

export const smallWithIcon: Story = {
  args: {
    ...Default.args,
    icon: <AddIcon />,
    iconVariant: 'start',
    size: 'small'
  }
};

export const smallDanger: Story = {
  args: {
    ...Default.args,
    isDanger: true,
    size: 'small'
  }
};
