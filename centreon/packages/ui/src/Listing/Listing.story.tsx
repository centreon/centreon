import { Button, Typography } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { useState } from 'react';

import Listing from '.';
import { type Column, ColumnType, type ComponentColumnProps, type RowRecord } from './models';

const ButtonColumn = ({ row }: ComponentColumnProps): JSX.Element => (
  <Button size="small">Click to reveal details about {String(row.name)}</Button>
);

const CheckboxColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const [checked, setChecked] = useState(false);

  return (
    <input
      aria-label={`Toggle ${String(row.name)}`}
      checked={checked}
      onChange={(event) => setChecked(event.target.checked)}
      type="checkbox"
    />
  );
};

const LargeText = (): JSX.Element => (
  <Typography sx={{ whiteSpace: 'normal' }}>This is a large text that fills the content</Typography>
);

const subItems = Array.from({ length: 10 }, (_, index) => ({
  active: false,
  description: `Sub item ${index * 10} description`,
  disableCheckbox: false,
  disableRow: false,
  id: index * 10,
  name: `Sub Item ${index * 10}`,
  selected: false
}));

const rows = (count: number) =>
  Array.from({ length: count }, (_, index) => ({
    active: false,
    description: `Entity ${index}`,
    disableCheckbox: false,
    disableRow: false,
    id: index,
    name: `E${index}`,
    selected: false,
    subItems: index % 2 === 0 ? subItems : undefined
  }));

const textColumns: Array<Column> = [
  { getFormattedString: ({ name }: RowRecord): string => String(name), id: 'name', label: 'Name', type: ColumnType.string },
  {
    getFormattedString: ({ description }: RowRecord): string => String(description),
    id: 'description',
    label: 'Description',
    type: ColumnType.string
  }
];

export const Responsive = ({ variant }: { variant: 'compact' | 'extended' }): JSX.Element => (
  <div style={{ height: '100vh' }}>
    <Listing
      columns={[
        ...textColumns,
        { Component: LargeText, id: '#', label: 'Custom', type: ColumnType.component, width: '100px' }
      ]}
      currentPage={1}
      isResponsive
      limit={10}
      listingVariant={variant === 'compact' ? ListingVariant.compact : ListingVariant.extended}
      rows={rows(10)}
      totalRows={10}
    />
  </div>
);

interface SubItemsProps {
  canCheckSubItems?: boolean;
  small?: boolean;
}

export const SubItems = ({ canCheckSubItems = false, small = false }: SubItemsProps): JSX.Element => {
  const [selectedRows, setSelectedRows] = useState<Array<unknown>>([]);

  return (
    <div style={{ height: '100vh' }}>
      <Listing
        checkable
        columns={[
          ...textColumns,
          {
            Component: ButtonColumn,
            displaySubItemsCaret: true,
            id: '#',
            label: 'Custom',
            type: ColumnType.component,
            width: '350px'
          }
        ]}
        currentPage={1}
        limit={10}
        onSelectRows={(selected) => setSelectedRows(selected)}
        rows={rows(small ? 3 : 10)}
        selectedRows={selectedRows as never}
        subItems={{
          canCheckSubItems,
          enable: true,
          getRowProperty: () => 'subItems',
          labelCollapse: 'Collapse',
          labelExpand: 'Expand'
        }}
        totalRows={10}
      />
    </div>
  );
};

// Records the row clicks in `row-clicks`.
export const ClickableColumn = (): JSX.Element => {
  const [clicks, setClicks] = useState(0);

  return (
    <div style={{ height: '100vh' }}>
      <Listing
        columns={[
          { Component: CheckboxColumn, clickable: true, id: 'toggle', label: 'Toggle', type: ColumnType.component }
        ]}
        currentPage={1}
        limit={10}
        onRowClick={() => setClicks((count) => count + 1)}
        rows={[{ id: 0, name: 'E0' }]}
        totalRows={1}
      />
      <output data-testid="row-clicks">{clicks}</output>
    </div>
  );
};
