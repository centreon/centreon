import * as React from 'react';

import {
  Checkbox,
  TableHead,
  TableRow,
  TableCell,
  withStyles,
  TableSortLabel,
  Typography,
} from '@material-ui/core';

import { useStyles as useCellStyles } from './ColumnCell';

const HeaderCell = withStyles((theme) => ({
  root: {
    backgroundColor: theme.palette.common.white,
    paddingBottom: theme.spacing(1),
    paddingTop: theme.spacing(1),
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
  headColumns;
  checkable: boolean;
  onRequestSort: (event, property) => void;
}

const ListingHeader = React.forwardRef(
  (
    {
      onSelectAllClick,
      order,
      orderBy,
      numSelected,
      rowCount,
      headColumns,
      checkable,
      onRequestSort,
    }: Props,
    ref,
  ): JSX.Element => {
    const classes = useCellStyles({ listingCheckable: checkable });

    const createSortHandler = (property) => (event): void => {
      onRequestSort(event, property);
    };

    const getSortField = (column): string => column.sortField || column.id;

    return (
      <TableHead ref={ref as React.RefObject<HTMLTableSectionElement>}>
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

          {headColumns.map((column) => (
            <HeaderCell
              key={column.id}
              align={column.numeric ? 'left' : 'inherit'}
              padding={column.disablePadding ? 'none' : 'default'}
              sortDirection={orderBy === column.id ? order : false}
              className={classes.cell}
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
  },
);

export default ListingHeader;
