import { Meta, StoryObj } from '@storybook/react';
import { List } from './index';

const meta: Meta<typeof List> = {
  component: List,
}

export default meta;
type Story = StoryObj<typeof List>

export const Default: Story = {
  args: {
    children: <>
      {[...Array(5)].map((_, i) => (
        <List.Item
          key={i}
          title={`List item ${i}`}
          description={`List item description ${i}`}
        />
      ))}
    </>,
  }
}

export const AsEmptyState: Story = {
  args: {
    children: <>
      <List.EmptyState
        labels={{
          title: 'No items found',
          actions: {
            create: 'Create item',
          }
        }}
      />
    </>,
    isEmpty: true,
  }
}
