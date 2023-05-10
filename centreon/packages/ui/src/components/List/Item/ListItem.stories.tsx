import { Meta, StoryObj } from '@storybook/react';

import { ListItem } from './ListItem';

const meta: Meta<typeof ListItem> = {
  component: ListItem
};

export default meta;
type Story = StoryObj<typeof ListItem>;

export const Default: Story = {
  args: {
    description: 'List item description',
    title: 'List item'
  }
};

export const WithActions: Story = {
  args: {
    ...Default.args,
    hasActions: true,
    hasCardAction: true
  }
};
