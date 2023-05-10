import { Meta, StoryObj } from '@storybook/react';
import { ListEmptyState } from './ListEmptyState';

const meta: Meta<typeof ListEmptyState> = {
  component: ListEmptyState,
}

export default meta;
type Story = StoryObj<typeof ListEmptyState>

export const Default: Story = {
  args: {
    labels: {
      title: 'No items found',
      actions: {
        create: 'Create item',
      }
    }
  }
}
