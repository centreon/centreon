import { Meta, StoryObj } from '@storybook/react';

import { ListEmptyState } from './ListEmptyState';

const meta: Meta<typeof ListEmptyState> = {
  component: ListEmptyState
};

export default meta;
type Story = StoryObj<typeof ListEmptyState>;

export const Default: Story = {
  args: {
    labels: {
      actions: {
        create: 'Create item'
      },
      title: 'No items found'
    }
  }
};
