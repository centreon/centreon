/* eslint-disable react/jsx-filename-extension */

import React from 'react';
import TableHead from '@material-ui/core/TableHead';
import TableRow from '@material-ui/core/TableRow';
import PropTypes from 'prop-types';
import TableCell from '@material-ui/core/TableCell';
import { withStyles } from '@material-ui/core/styles';
import StyledTableSortLabel from './StyledTableSortLabel';
import StyledCheckbox from './StyledCheckbox';
import TABLE_COLUMN_TYPES from '../ColumnTypes';

const HeaderCell = withStyles({
  root: {
    backgroundColor: '#009fdf',
    color: '#fff',
    lineHeight: 1.4,
    height: 24,
    padding: '3px 4px',
  },
})(TableCell);

const EnhancedTableHead = ({
  onSelectAllClick,
  order,
  orderBy,
  numSelected,
  rowCount,
  headRows,
  checkable,
  onRequestSort,
  indicatorsEditor,
}) => {
  const createSortHandler = (property) => (event) => {
    onRequestSort(event, property);
  };

  return (
    <TableHead>
      <TableRow>
        {checkable ? (
          <HeaderCell align="left" padding="checkbox">
            <StyledCheckbox
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
            {row.type === TABLE_COLUMN_TYPES.multicolumn ? (
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
        {indicatorsEditor && numSelected > 0 ? (
          <>
            <HeaderCell key="modeKpi" align="left" padding="none">
              Mode
            </HeaderCell>
            <HeaderCell key="warningKpi" align="left" padding="none">
              Warning
            </HeaderCell>
            <HeaderCell key="criticalKpi" align="left" padding="none">
              Critical
            </HeaderCell>
            <HeaderCell key="unknownKpi" align="left" padding="none">
              Unknown
            </HeaderCell>
          </>
        ) : null}
      </TableRow>
    </TableHead>
  );
};

EnhancedTableHead.propTypes = {
  numSelected: PropTypes.number.isRequired,
  onRequestSort: PropTypes.func.isRequired,
  onSelectAllClick: PropTypes.func.isRequired,
  order: PropTypes.string.isRequired,
  orderBy: PropTypes.string.isRequired,
  rowCount: PropTypes.number.isRequired,
  headRows: PropTypes.arrayOf(
    PropTypes.objectOf(
      PropTypes.oneOfType([PropTypes.bool, PropTypes.string, PropTypes.number]),
    ),
  ).isRequired,
  checkable: PropTypes.bool.isRequired,
  indicatorsEditor: PropTypes.bool,
};

EnhancedTableHead.defaultProps = {
  indicatorsEditor: null,
};

export default EnhancedTableHead;
