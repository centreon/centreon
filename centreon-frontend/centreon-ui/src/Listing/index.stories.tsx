/* eslint-disable react/prop-types */

import React from 'react';

import { makeStyles, Button } from '@material-ui/core';
import { grey } from '@material-ui/core/colors';

import { ColumnType } from './models';

import Listing from '.';

export default { title: 'Listing' };

const useStyles = makeStyles((theme) => ({
  listing: {
    backgroundColor: theme.palette.background.default,
  },
}));

const ComponentColumn = ({ row, isRowSelected }): JSX.Element => (
  <>
    <span>
      {'I am '}
      <b>{`${isRowSelected ? 'selected' : 'not selected'}`}</b>
      {' / '}
    </span>
    <span>
      {'I am '}
      <b>{`${row.active ? 'active' : 'not active'}`}</b>
    </span>
  </>
);

const configuration = [
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

const noOp = (): void => undefined;

const tenElements = new Array(10).fill(0);

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

const Story = ({ disableCheckable = false, ...props }): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.listing}>
      <Listing
        columnConfiguration={configuration}
        onSort={noOp}
        onPaginate={noOp}
        onPaginationLimitChanged={noOp}
        limit={listing.length}
        currentPage={0}
        totalRows={listing.length}
        tableData={listing}
        rowColorConditions={rowColorConditions}
        selectedRows={listing.filter((row) => row.selected)}
        checkable={!disableCheckable}
        disableRowCheckCondition={(row): boolean => row.disableCheckbox}
        {...props}
      />
    </div>
  );
};

export const normal = (): JSX.Element => <Story />;

export const loadingWithNoData = (): JSX.Element => {
  return <Story tableData={[]} totalRows={0} loading />;
};

export const loadingWithData = (): JSX.Element => {
  return <Story loading />;
};

const Actions = (
  <Button variant="contained" color="primary" size="small">
    Action
  </Button>
);

export const withActions = (): JSX.Element => <Story Actions={Actions} />;

export const withoutCheckboxes = (): JSX.Element => <Story disableCheckable />;
