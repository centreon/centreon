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
    id: 'name',
    label: 'Name',
    type: ColumnType.string,
    getFormattedString: ({ name }): string => name,
  },
  {
    id: 'description',
    label: 'Description',
    type: ColumnType.string,
    getFormattedString: ({ description }): string => description,
  },
  {
    id: '#',
    label: 'Custom',
    type: ColumnType.component,
    Component: ComponentColumn,
  },
];

const tenElements = new Array(10).fill(0);

interface Entity {
  id: number;
  name: string;
  description: string;
  active: boolean;
  selected: boolean;
  disableCheckbox: boolean;
}

const listing = [...tenElements].map((_, index) => ({
  id: index,
  name: `E${index}`,
  description: `Entity ${index}`,
  active: index % 2 === 0,
  selected: index % 3 === 0,
  disableCheckbox: index % 4 === 0,
}));

const rowColorConditions = [
  {
    name: 'inactive',
    condition: ({ active }): boolean => !active,
    color: grey[500],
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
        limit={listing.length}
        currentPage={0}
        totalRows={listing.length}
        rows={listing}
        rowColorConditions={rowColorConditions}
        selectedRows={listing.filter((row) => row.selected)}
        disableRowCheckCondition={(row): boolean => row.disableCheckbox}
        {...props}
      />
    </div>
  );
};

export const normal = (): JSX.Element => <Story />;

export const loadingWithNoData = (): JSX.Element => {
  return <Story rows={[]} totalRows={0} loading />;
};

export const loadingWithData = (): JSX.Element => {
  return <Story loading />;
};

const actions = (
  <Button variant="contained" color="primary" size="small">
    Action
  </Button>
);

export const withActions = (): JSX.Element => <Story actions={actions} />;

export const withoutCheckboxes = (): JSX.Element => <Story checkable={false} />;

const ListingWithSortableColumns = (): JSX.Element => {
  const defaultColumnIds = defaultColumns.map(prop('id'));

  const [selectedColumnIds, setSelectedColumnIds] = React.useState<
    Array<string>
  >(defaultColumnIds);

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultColumnIds);
  };

  return (
    <Story
      columnConfiguration={{
        sortable: true,
        selectedColumnIds,
      }}
      columns={defaultColumns}
      onSelectColumns={setSelectedColumnIds}
      onResetColumns={resetColumns}
    />
  );
};

export const withEditableColumns = (): JSX.Element => (
  <ListingWithSortableColumns />
);
