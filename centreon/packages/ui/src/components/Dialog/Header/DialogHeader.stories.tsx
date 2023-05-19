import { Meta, StoryObj } from '@storybook/react';

import { DialogHeader } from './DialogHeader';

const meta: Meta<typeof DialogHeader> = {
  component: DialogHeader
};

export default meta;
type Story = StoryObj<typeof DialogHeader>;

export const Default: Story = {
  args: {
    children: 'Dialog Header',
    hasCloseButton: true
  }
};
