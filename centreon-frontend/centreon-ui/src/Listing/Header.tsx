import React from 'react';
import TableHead from '@material-ui/core/TableHead';
import TableRow from '@material-ui/core/TableRow';
import TableCell from '@material-ui/core/TableCell';
import { withStyles } from '@material-ui/core/styles';
import StyledTableSortLabel from './SortLabel';
import StyledCheckbox from './Checkbox';
import TABLE_COLUMN_TYPES from './ColumnTypes';

const HeaderCell = withStyles({
  root: {
    backgroundColor: '#009fdf',
    color: '#fff',
    lineHeight: 1.4,
    height: 24,
    padding: '3px 4px',
  },
})(TableCell);

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
            {row.type === TABLE_COLUMN_TYPES.multicolumn ||
            row.sortable === false ? (
              row.label
            ) : (
              <StyledTableSortLabel
                active={orderBy === row.id}
                direction={order || 'desc'}
                onClick={createSortHandler(row.id)}
                icon={{ color: 'red' }}
              >
                {row.label}
              </StyledTableSortLabel>
            )}
          </HeaderCell>
        ))}
      </TableRow>
    </TableHead>
  );
};

export default ListingHeader;
