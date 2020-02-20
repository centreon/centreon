import React from 'react';

import {
  TableHead,
  TableRow,
  TableCell,
  withStyles,
  TableSortLabel,
  Typography,
} from '@material-ui/core';

import StyledCheckbox from './Checkbox';

const HeaderCell = withStyles((theme) => ({
  root: {
    backgroundColor: theme.palette.common.white,
    lineHeight: 1.4,
    height: 24,
    padding: '3px 4px',
  },
}))(TableCell);

interface Props {
  onSelectAllClick: (event) => void;
  order?: 'desc' | 'asc';
  orderBy?: string;
  numSelected: number;
  rowCount: number;
  headRows;
  checkable: boolean;
  onRequestSort: (event, property) => void;
}

const ListingHeader = ({
  onSelectAllClick,
  order,
  orderBy,
  numSelected,
  rowCount,
  headRows,
  checkable,
  onRequestSort,
}: Props): JSX.Element => {
  const createSortHandler = (property) => (event): void => {
    onRequestSort(event, property);
  };

  return (
    <TableHead>
      <TableRow>
        {checkable ? (
          <HeaderCell align="left" padding="checkbox">
            <StyledCheckbox
              inputProps={{ 'aria-label': 'Select all' }}
              indeterminate={numSelected > 0 && numSelected < rowCount}
              checked={numSelected === rowCount}
              onChange={onSelectAllClick}
            />
          </HeaderCell>
        ) : null}

        {headRows.map((row) => (
          <HeaderCell
            key={row.id}
            align={row.numeric ? 'left' : 'inherit'}
            padding={row.disablePadding ? 'none' : 'default'}
            sortDirection={orderBy === row.id ? order : false}
          >
            {row.sortable === false ? (
              <Typography variant="subtitle1">{row.label}</Typography>
            ) : (
              <TableSortLabel
                active={orderBy === row.id}
                direction={order || 'desc'}
                onClick={createSortHandler(row.id)}
              >
                <Typography variant="subtitle1">{row.label}</Typography>
              </TableSortLabel>
            )}
          </HeaderCell>
        ))}
      </TableRow>
    </TableHead>
  );
};

export default ListingHeader;
