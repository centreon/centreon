import { Meta, StoryObj } from '@storybook/react';

import Avatar from './Avatar';
import '../../ThemeProvider/tailwindcss.css';

const meta: Meta<typeof Avatar> = {
  component: Avatar
};

export default meta;
type Story = StoryObj<typeof Avatar>;

export const Default: Story = {
  args: {
    children: 'Label'
  }
};

export const compact: Story = {
  args: {
    ...Default.args,
    compact: true
  }
};
