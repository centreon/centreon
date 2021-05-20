/* eslint-disable react/prop-types */

import React from 'react';

import { prop } from 'ramda';

import { makeStyles, Button } from '@material-ui/core';
import { grey } from '@material-ui/core/colors';

import { ListingProps } from '..';

import { Column, ColumnType } from './models';

import Listing from '.';

export default { title: 'Listing' };

const useStyles = makeStyles((theme) => ({
  listing: {
    backgroundColor: theme.palette.background.default,
  },
}));

const ComponentColumn = ({ row, isSelected }): JSX.Element => (
  <>
    <span>
      {'I am '}
      <b>{`${isSelected ? 'selected' : 'not selected'}`}</b>
      {' / '}
    </span>
    <span>
      {'I am '}
      <b>{`${row.active ? 'active' : 'not active'}`}</b>
    </span>
  </>
);

const defaultColumns = [
  {
    getFormattedString: ({ name }): string => name,
    id: 'name',
    label: 'Name',
    type: ColumnType.string,
  },
  {
    getFormattedString: ({ description }): string => description,
    id: 'description',
    label: 'Description',
    type: ColumnType.string,
  },
  {
    Component: ComponentColumn,
    id: '#',
    label: 'Custom',
    type: ColumnType.component,
  },
];

const tenElements = new Array(10).fill(0);

interface Entity {
  active: boolean;
  description: string;
  disableCheckbox: boolean;
  id: number;
  name: string;
  selected: boolean;
}

const listing = [...tenElements].map((_, index) => ({
  active: index % 2 === 0,
  description: `Entity ${index}`,
  disableCheckbox: index % 4 === 0,
  disableRow: index % 5 === 0,
  id: index,
  name: `E${index}`,
  selected: index % 3 === 0,
}));

const rowColorConditions = [
  {
    color: grey[500],
    condition: ({ active }): boolean => !active,
    name: 'inactive',
  },
];

const Story = ({
  columns = defaultColumns,
  ...props
}: Omit<ListingProps<Entity>, 'columns'> & {
  columns?: Array<Column>;
}): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.listing}>
      <Listing
        columns={columns}
        currentPage={0}
        disableRowCheckCondition={(row): boolean => row.disableCheckbox}
        disableRowCondition={(row): boolean => row.disableRow}
        limit={listing.length}
        rowColorConditions={rowColorConditions}
        rows={listing}
        selectedRows={listing.filter((row) => row.selected)}
        totalRows={listing.length}
        {...props}
      />
    </div>
  );
};

export const normal = (): JSX.Element => <Story />;

export const loadingWithNoData = (): JSX.Element => {
  return <Story loading rows={[]} totalRows={0} />;
};

export const loadingWithData = (): JSX.Element => {
  return <Story loading />;
};

const actions = (
  <Button color="primary" size="small" variant="contained">
    Action
  </Button>
);

export const withActions = (): JSX.Element => <Story actions={actions} />;

export const withoutCheckboxes = (): JSX.Element => <Story checkable={false} />;

const ListingWithSortableColumns = (): JSX.Element => {
  const defaultColumnIds = defaultColumns.map(prop('id'));

  const [selectedColumnIds, setSelectedColumnIds] =
    React.useState<Array<string>>(defaultColumnIds);

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultColumnIds);
  };

  return (
    <Story
      columnConfiguration={{
        selectedColumnIds,
        sortable: true,
      }}
      columns={defaultColumns}
      onResetColumns={resetColumns}
      onSelectColumns={setSelectedColumnIds}
    />
  );
};

export const withEditableColumns = (): JSX.Element => (
  <ListingWithSortableColumns />
);
