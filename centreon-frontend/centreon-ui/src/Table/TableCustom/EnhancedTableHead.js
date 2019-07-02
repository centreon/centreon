import React, { Component } from "react";
import StyledTableCell from "./StyledTableCell";
import StyledTableSortLabel from "./StyledTableSortLabel";
import StyledCheckbox from "./StyledCheckbox";
import TableHead from "@material-ui/core/TableHead";
import TableRow from "@material-ui/core/TableRow";
import PropTypes from "prop-types";
import TABLE_COLUMN_TYPES from '../ColumnTypes';

class EnhancedTableHead extends Component {
  createSortHandler = property => event => {
    const { onRequestSort } = this.props;
    onRequestSort(event, property);
  };
  render() {
    const {
      onSelectAllClick,
      order,
      orderBy,
      numSelected,
      rowCount,
      headRows,
      checkable
    } = this.props;
    return (
      <TableHead>
        <TableRow>
          {checkable ? (
            <StyledTableCell align="left" padding="checkbox">
              <StyledCheckbox
                indeterminate={numSelected > 0 && numSelected < rowCount}
                checked={numSelected === rowCount}
                onChange={onSelectAllClick}
              />
            </StyledTableCell>
          ) : null}

          {headRows.map(row => (
            <StyledTableCell
              key={row.id}
              align={row.numeric ? "left" : ""}
              padding={row.disablePadding ? "none" : "default"}
              sortDirection={orderBy === row.id ? order : false}
            >
              {row.type === TABLE_COLUMN_TYPES.multicolumn ? (
                row.label
              ) : (
                <StyledTableSortLabel
                  active={orderBy === row.id}
                  direction={order}
                  onClick={this.createSortHandler(row.id)}
                  icon={{ color: "red" }}
                >
                  {row.label}
                </StyledTableSortLabel>
              )}
            </StyledTableCell>
          ))}
        </TableRow>
      </TableHead>
    );
  }
}

EnhancedTableHead.propTypes = {
  numSelected: PropTypes.number.isRequired,
  onRequestSort: PropTypes.func.isRequired,
  onSelectAllClick: PropTypes.func.isRequired,
  order: PropTypes.string.isRequired,
  orderBy: PropTypes.string.isRequired,
  rowCount: PropTypes.number.isRequired
};

export default EnhancedTableHead;
