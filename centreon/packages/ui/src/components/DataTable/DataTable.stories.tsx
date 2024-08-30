import { Meta, StoryObj } from '@storybook/react';

import { Box } from '@mui/material';

import { ColumnType } from '../../Listing/models';

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
            key={`k-${
              // biome-ignore lint/suspicious/noArrayIndexKey:
              i
            }`}
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

const ListingTemplate = (args): JSX.Element => (
  <Box sx={{ height: '80vh' }}>
    <DataTable {...args} />
  </Box>
);

export const listing: Story = {
  args: {
    children: (
      <DataTable.Listing
        columns={[
          {
            getFormattedString: (row) => row.title,
            id: 'title',
            label: 'Title',
            type: ColumnType.string
          },
          {
            getFormattedString: (row) => row.description,
            id: 'description',
            label: 'Description',
            type: ColumnType.string
          }
        ]}
        rows={[...Array(5)].map((_, i) => ({
          description: `Item description ${i}`,
          id: i,
          title: `Item ${i}`
        }))}
      />
    ),
    variant: 'listing'
  },
  render: ListingTemplate
};
