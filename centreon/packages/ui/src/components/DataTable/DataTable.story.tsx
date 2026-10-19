import Delete from '@mui/icons-material/Delete';
import { Box } from '@mui/material';

import { IconButton } from '../..';
import { ColumnType } from '../../Listing/models';
import { DataTable } from '.';

const data = Array.from({ length: 5 }, (_, id) => ({
  description: `Description ${id}`,
  id,
  title: `Entity ${id}`
}));

const CardActions = (): JSX.Element => (
  <IconButton ariaLabel="Delete" onClick={() => undefined} title="Delete">
    <Delete fontSize="small" />
  </IconButton>
);

interface GridProps {
  hasActions?: boolean;
  hasCardAction?: boolean;
}

export const Grid = ({
  hasActions = false,
  hasCardAction = false
}: GridProps): JSX.Element => (
  <DataTable variant="grid">
    {data.map(({ title, description }) => (
      <DataTable.Item
        Actions={<CardActions />}
        description={description}
        hasActions={hasActions}
        hasCardAction={hasCardAction}
        key={title}
        title={title}
      />
    ))}
  </DataTable>
);

export const Empty = ({
  canCreate = false
}: {
  canCreate?: boolean;
}): JSX.Element => (
  <DataTable isEmpty variant="grid">
    <DataTable.EmptyState
      canCreate={canCreate}
      labels={{
        actions: { create: 'Create' },
        description: 'Description',
        title: 'Welcome'
      }}
      onCreate={() => undefined}
    />
  </DataTable>
);

export const Listing = (): JSX.Element => (
  <Box sx={{ height: '100vh' }}>
    <DataTable variant="listing">
      <DataTable.Listing
        columns={[
          {
            getFormattedString: (row) => String(row.title),
            id: 'title',
            label: 'Title',
            type: ColumnType.string
          },
          {
            getFormattedString: (row) => String(row.description),
            id: 'description',
            label: 'Description',
            type: ColumnType.string
          }
        ]}
        rows={data}
      />
    </DataTable>
  </Box>
);
