/* eslint-disable react/jsx-no-bind */
/* eslint-disable react/jsx-filename-extension */

import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { withStyles } from '@material-ui/core/styles';
import Table from '@material-ui/core/Table';
import TableBody from '@material-ui/core/TableBody';
import Paper from '@material-ui/core/Paper';
import StyledTableRow from './StyledTableRow';
import IconPowerSettings from '../../MaterialComponents/Icons/IconPowerSettings';
import IconPowerSettingsDisable from '../../MaterialComponents/Icons/IconPowerSettingsDisable';
import StyledCheckbox from './StyledCheckbox';
import IconDelete from '../../MaterialComponents/Icons/IconDelete';
import IconLibraryAdd from '../../MaterialComponents/Icons/IconLibraryAdd';
import EnhancedTableHead from './EnhancedTableHead';
import TABLE_COLUMN_TYPES from '../ColumnTypes';
import TablePaginationActions from './TablePaginationActions';
import StyledTableCell2 from './StyledTableCell2';
import TableCellCustom from './TableCellCustom';
import StyledPagination from './StyledPagination';
import Tooltip from '../../MaterialComponents/Tooltip';

const styles = (theme) => ({
  root: {
    width: '100%',
  },
  paper: {
    width: '100%',
    marginBottom: theme.spacing(2),
  },
  tableWrapper: {
    overflowX: 'auto',
    overflowY: 'hidden',
  },
  rowDisabled: {
    backgroundColor: 'rgba(0, 0, 0, 0.07) !important',
  },
});

class TableCustom extends Component {
  state = {
    order: '',
    orderBy: '',
  };

  handleRequestSort = (event, property) => {
    const { onSort } = this.props;
    const { orderBy, order } = this.state;
    const isDesc = orderBy === property && order === 'desc';
    this.setState(
      {
        order: isDesc ? 'asc' : 'desc',
        orderBy: property,
      },
      () => {
        onSort({
          order: isDesc ? 'asc' : 'desc',
          orderBy: property,
        });
      },
    );
  };

  handleSelectAllClick = (event) => {
    const { onTableSelectionChanged, tableData } = this.props;
    if (event.target.checked) {
      const newSelecteds = tableData.map((n) => n.id);
      onTableSelectionChanged(newSelecteds);
      return;
    }

    onTableSelectionChanged([]);
  };

  handleClick = (event, name) => {
    event.preventDefault();
    event.stopPropagation();
    const { onTableSelectionChanged, selected } = this.props;
    const selectedIndex = selected.indexOf(name);
    let newSelected = [];

    if (selectedIndex === -1) {
      newSelected = newSelected.concat(selected, name);
    } else if (selectedIndex === 0) {
      newSelected = newSelected.concat(selected.slice(1));
    } else if (selectedIndex === selected.length - 1) {
      newSelected = newSelected.concat(selected.slice(0, -1));
    } else if (selectedIndex > 0) {
      newSelected = newSelected.concat(
        selected.slice(0, selectedIndex),
        selected.slice(selectedIndex + 1),
      );
    }
    onTableSelectionChanged(newSelected);
  };

  rowHovered = (id, value) => {
    this.setState({
      hovered: value ? id : null,
    });
  };

  addConditionalRowBackground = (
    row,
    column,
    backgroundClass,
    attribute,
    classes,
  ) => {
    return column
      ? {
          [attribute]: !row[column] ? classes[backgroundClass] : '',
        }
      : {};
  };

  render() {
    const {
      columnConfiguration,
      tableData,
      onDelete,
      onPaginate,
      onDuplicate,
      onPaginationLimitChanged,
      limit,
      checkable,
      currentPage,
      classes,
      totalRows,
      onEnable,
      onDisable,
      onRowClick,
      selected,
      enabledColumn,
    } = this.props;
    const { order, orderBy, hovered } = this.state;

    const isSelected = (name) => selected.indexOf(name) !== -1;

    const emptyRows = limit - Math.min(limit, totalRows - currentPage * limit);

    return (
      <div className={classes.root}>
        <Paper className={classes.paper}>
          <div className={classes.tableWrapper}>
            <Table
              className={classes.table}
              aria-labelledby="tableTitle"
              size="small"
            >
              <EnhancedTableHead
                numSelected={selected.length}
                order={order}
                checkable={checkable}
                orderBy={orderBy}
                onSelectAllClick={this.handleSelectAllClick}
                onRequestSort={this.handleRequestSort}
                rowCount={limit - emptyRows}
                onClick={onRowClick}
                className={classes.tableWrapper}
                headRows={columnConfiguration}
              />
              <TableBody onMouseLeave={this.rowHovered.bind(this, '', false)}>
                {tableData.map((row) => {
                  const isItemSelected = isSelected(row.id);
                  return (
                    <StyledTableRow
                      hover
                      role="checkbox"
                      aria-checked={isItemSelected}
                      tabIndex={-1}
                      key={row.id}
                      selected={isItemSelected}
                      onMouseEnter={this.rowHovered.bind(this, row.id, true)}
                      {...this.addConditionalRowBackground(
                        row,
                        enabledColumn,
                        'rowDisabled',
                        'className',
                        classes,
                      )}
                    >
                      {checkable ? (
                        <StyledTableCell2
                          align="left"
                          onClick={(event) => this.handleClick(event, row.id)}
                          className={classes.tableCell}
                          padding="checkbox"
                        >
                          <StyledCheckbox
                            checked={isItemSelected}
                            color="primary"
                          />
                        </StyledTableCell2>
                      ) : null}

                      {columnConfiguration.map((column) => {
                        switch (column.type) {
                          case TABLE_COLUMN_TYPES.string:
                          case TABLE_COLUMN_TYPES.number:
                            return (
                              <TableCellCustom
                                key={column.id}
                                align="left"
                                className={classes.tableCellCustom}
                              >
                                {row[column.id] || ''}
                              </TableCellCustom>
                            );
                          case TABLE_COLUMN_TYPES.toggler:
                            return (
                              <StyledTableCell2 key={column.id} align="left">
                                {row[column.id] ? (
                                  <Tooltip
                                    label="Enable/Disable"
                                    customStyle={{
                                      position: 'absolute',
                                      top: -1,
                                      width: 31,
                                      height: 31,
                                    }}
                                    onClick={() => {
                                      onDisable([row.id]);
                                    }}
                                  >
                                    <IconPowerSettings
                                      label="Disable"
                                      active
                                      customStyle={{
                                        fontSize: 18,
                                        boxSizing: 'border-box',
                                        position: 'relative',
                                        top: -2,
                                      }}
                                    />
                                  </Tooltip>
                                ) : (
                                  <Tooltip
                                    label="Enable/Disable"
                                    customStyle={{
                                      position: 'absolute',
                                      top: -1,
                                      width: 31,
                                      height: 31,
                                    }}
                                    onClick={() => {
                                      onEnable([row.id]);
                                    }}
                                  >
                                    <IconPowerSettingsDisable
                                      active
                                      label="Disable"
                                      customStyle={{
                                        fontSize: 18,
                                        boxSizing: 'border-box',
                                        position: 'relative',
                                        top: -2,
                                      }}
                                    />
                                  </Tooltip>
                                )}
                              </StyledTableCell2>
                            );
                          case TABLE_COLUMN_TYPES.multicolumn:
                            return (
                              <TableCellCustom
                                key={column.id}
                                align="left"
                                className={classes.tableCellCustom}
                              >
                                {column.columns.map((subColumn) => (
                                  <React.Fragment>
                                    {`${subColumn.label} ${row[subColumn.id]}`}
                                    {subColumn.type === 'percentage'
                                      ? '%'
                                      : null}
                                    {'   '}
                                  </React.Fragment>
                                ))}
                              </TableCellCustom>
                            );
                          case TABLE_COLUMN_TYPES.hoverActions:
                            return (
                              <StyledTableCell2
                                key={column.id}
                                style={{
                                  paddingTop: 0,
                                  paddingBottom: 0,
                                  width: 60,
                                  boxSizing: 'border-box',
                                  padding: '0 !important',
                                }}
                              >
                                {hovered === row.id ? (
                                  <React.Fragment>
                                    <Tooltip
                                      label="Delete"
                                      position={{ left: '50px' }}
                                      customStyle={{
                                        position: 'absolute',
                                        right: 28,
                                        top: 0,
                                        padding: '3px 5px',
                                      }}
                                      onClick={() => {
                                        onDelete([row.id]);
                                      }}
                                    >
                                      <IconDelete
                                        customStyle={{
                                          color: '#707070',
                                          fontSize: 21,
                                        }}
                                      />
                                    </Tooltip>
                                    <Tooltip
                                      label="Duplicate"
                                      position={{ left: '50px' }}
                                      customStyle={{
                                        position: 'absolute',
                                        right: -5,
                                        top: 0,
                                        padding: '3px 5px',
                                      }}
                                      onClick={() => {
                                        onDuplicate([row.id]);
                                      }}
                                    >
                                      <IconLibraryAdd
                                        customStyle={{
                                          color: '#707070',
                                          fontSize: 20,
                                        }}
                                      />
                                    </Tooltip>
                                  </React.Fragment>
                                ) : (
                                  ' '
                                )}
                              </StyledTableCell2>
                            );
                          default:
                            return null;
                        }
                      })}
                    </StyledTableRow>
                  );
                })}
              </TableBody>
            </Table>
          </div>
          <StyledPagination
            rowsPerPageOptions={[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]}
            colSpan={3}
            count={totalRows}
            rowsPerPage={limit}
            page={currentPage}
            style={{ display: 'flex', flexDirection: 'row-reverse' }}
            SelectProps={{
              native: true,
            }}
            onChangePage={onPaginate}
            onChangeRowsPerPage={onPaginationLimitChanged}
            ActionsComponent={TablePaginationActions}
          />
        </Paper>
      </div>
    );
  }
}

TableCustom.defaultProps = {
  enabledColumn: '',
  onRowClick: () => {},
};

const anyObject = PropTypes.objectOf(
  PropTypes.oneOfType([PropTypes.bool, PropTypes.string, PropTypes.number]),
);
const anyArray = PropTypes.arrayOf(anyObject);

TableCustom.propTypes = {
  classes: anyObject.isRequired,
  onSort: PropTypes.func.isRequired,
  onTableSelectionChanged: PropTypes.func.isRequired,
  columnConfiguration: anyArray.isRequired,
  tableData: anyArray.isRequired,
  onDelete: PropTypes.func.isRequired,
  onPaginate: PropTypes.func.isRequired,
  onDuplicate: PropTypes.func.isRequired,
  onPaginationLimitChanged: PropTypes.func.isRequired,
  limit: PropTypes.number.isRequired,
  checkable: PropTypes.bool.isRequired,
  currentPage: PropTypes.number.isRequired,
  totalRows: PropTypes.number.isRequired,
  onEnable: PropTypes.func.isRequired,
  onDisable: PropTypes.func.isRequired,
  onRowClick: PropTypes.func,
  selected: anyArray.isRequired,
  enabledColumn: PropTypes.string,
};

export default withStyles(styles, { withTheme: true })(TableCustom);
