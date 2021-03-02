import * as React from 'react';

import { equals } from 'ramda';
import clsx from 'clsx';

import {
  TableHead,
  TableRow,
  TableCell,
  withStyles,
  TableSortLabel,
  Typography,
  makeStyles,
} from '@material-ui/core';

import { useStyles as useCellStyles } from './Cell/DataCell';
import Checkbox from './Checkbox';

const height = 28;

const HeaderCell = withStyles((theme) => ({
  root: {
    backgroundColor: theme.palette.common.white,
    padding: theme.spacing(0, 0, 0, 1.5),
    height,
  },
}))(TableCell);

const HeaderTypography = withStyles({
  root: {
    fontWeight: 'bold',
  },
})(Typography);

const useStyles = makeStyles((theme) => ({
  row: {
    display: 'contents',
  },
  compactCell: {
    paddingLeft: theme.spacing(0.5),
  },
}));

interface Props {
  onSelectAllClick: (event) => void;
  order?: 'desc' | 'asc';
  orderBy?: string;
  numSelected: number;
  rowCount: number;
  headColumns;
  checkable: boolean;
  onRequestSort: (event, property) => void;
}

const ListingHeader = ({
  onSelectAllClick,
  order,
  orderBy,
  numSelected,
  rowCount,
  headColumns,
  checkable,
  onRequestSort,
}: Props): JSX.Element => {
  const classes = useStyles();
  const cellClasses = useCellStyles({ listingCheckable: checkable });

  const createSortHandler = (property) => (event): void => {
    onRequestSort(event, property);
  };

  const getSortField = (column): string => column.sortField || column.id;

  return (
    <TableHead className={classes.row}>
      <TableRow className={classes.row}>
        {checkable && (
          <HeaderCell>
            <Checkbox
              inputProps={{ 'aria-label': 'Select all' }}
              indeterminate={numSelected > 0 && numSelected < rowCount}
              checked={numSelected === rowCount}
              onChange={onSelectAllClick}
            />
          </HeaderCell>
        )}

        {headColumns.map((column) => (
          <HeaderCell
            key={column.id}
            align={column.numeric ? 'left' : 'inherit'}
            padding={column.compact ? 'none' : 'default'}
            sortDirection={orderBy === column.id ? order : false}
            className={clsx(
              cellClasses.cell,
              column.compact && classes.compactCell,
            )}
          >
            {column.sortable === false ? (
              <HeaderTypography variant="body2">
                {column.label}
              </HeaderTypography>
            ) : (
              <TableSortLabel
                aria-label={`Column ${column.label}`}
                active={orderBy === getSortField(column)}
                direction={order || 'desc'}
                onClick={createSortHandler(getSortField(column))}
              >
                <HeaderTypography variant="body2">
                  {column.label}
                </HeaderTypography>
              </TableSortLabel>
            )}
          </HeaderCell>
        ))}
      </TableRow>
    </TableHead>
  );
};

const MemoizedListingHeader = React.memo(
  ListingHeader,
  (prevProps, nextProps) =>
    equals(prevProps.order, nextProps.order) &&
    equals(prevProps.orderBy, nextProps.orderBy) &&
    equals(prevProps.numSelected, nextProps.numSelected) &&
    equals(prevProps.rowCount, nextProps.rowCount) &&
    equals(prevProps.headColumns, nextProps.headColumns) &&
    equals(prevProps.checkable, nextProps.checkable),
);

export default MemoizedListingHeader;
export { height as headerHeight };
