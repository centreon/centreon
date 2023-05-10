import { Meta, StoryObj } from '@storybook/react';

import { List } from './index';

const meta: Meta<typeof List> = {
  component: List
};

export default meta;
type Story = StoryObj<typeof List>;

export const Default: Story = {
  args: {
    children: (
      <>
        {[...Array(5)].map((_, i) => (
          <List.Item
            description={`List item description ${i}`}
            key={i}
            title={`List item ${i}`}
          />
        ))}
      </>
    )
  }
};

export const AsEmptyState: Story = {
  args: {
    children: (
      <List.EmptyState
        labels={{
          actions: {
            create: 'Create item'
          },
          title: 'No items found'
        }}
      />
    ),
    isEmpty: true
  }
};
