import { Meta, StoryObj } from '@storybook/react';

import GridEmptyState from './GridEmptyState';

const meta: Meta<typeof GridEmptyState> = {
  component: GridEmptyState
};

export default meta;
type Story = StoryObj<typeof GridEmptyState>;

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
