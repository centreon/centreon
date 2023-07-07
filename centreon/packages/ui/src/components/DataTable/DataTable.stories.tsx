import { Meta, StoryObj } from '@storybook/react';

import { DataTable } from './index';

const meta: Meta<typeof DataTable> = {
  component: DataTable
};

export default meta;
type Story = StoryObj<typeof DataTable>;

export const Default: Story = {
  args: {
    children: (
      <>
        {[...Array(5)].map((_, i) => (
          <DataTable.Item
            description={`Item description ${i}`}
            key={`k-${i}`} // eslint-disable-line react/no-array-index-key
            title={`Item ${i}`}
          />
        ))}
      </>
    )
  }
};

export const AsEmptyState: Story = {
  args: {
    children: (
      <DataTable.EmptyState
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

export const withFixedHeightContainer: Story = {
  args: { ...Default.args },
  render: (args) => (
    <div style={{ height: '400px' }}>
      <DataTable {...args} />
    </div>
  )
};
