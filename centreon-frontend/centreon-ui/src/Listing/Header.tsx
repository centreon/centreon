import React from 'react';

import {
  Checkbox,
  TableHead,
  TableRow,
  TableCell,
  withStyles,
  TableSortLabel,
  Typography,
} from '@material-ui/core';

const HeaderCell = withStyles((theme) => ({
  root: {
    backgroundColor: theme.palette.common.white,
    padding: theme.spacing(1),
  },
}))(TableCell);

const HeaderTypography = withStyles({
  root: {
    fontWeight: 'bold',
  },
})(Typography);

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
          <HeaderCell padding="checkbox">
            <Checkbox
              size="small"
              color="primary"
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
              <HeaderTypography variant="body2">{row.label}</HeaderTypography>
            ) : (
              <TableSortLabel
                active={orderBy === row.id}
                direction={order || 'desc'}
                onClick={createSortHandler(row.id)}
              >
                <HeaderTypography variant="body2">{row.label}</HeaderTypography>
              </TableSortLabel>
            )}
          </HeaderCell>
        ))}
      </TableRow>
    </TableHead>
  );
};

export default ListingHeader;
