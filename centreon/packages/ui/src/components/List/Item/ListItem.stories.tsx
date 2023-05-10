import { Meta, StoryObj } from '@storybook/react';
import { ListItem } from './ListItem';

const meta: Meta<typeof ListItem> = {
  component: ListItem,
}

export default meta;
type Story = StoryObj<typeof ListItem>

export const Default: Story = {
  args: {
    title: 'List item',
    description: 'List item description',
  }
}

export const WithActions: Story = {
  args: {
    ...Default.args,
    hasCardAction: true,
    hasActions: true,
  }
}