/* eslint-disable no-plusplus */
/* eslint-disable no-nested-ternary */
/* eslint-disable react/jsx-no-bind */
/* eslint-disable react/jsx-filename-extension */

import React from 'react';

import PropTypes from 'prop-types';
import clsx from 'clsx';
import ResizeObserver from 'resize-observer-polyfill';

import { withStyles } from '@material-ui/core/styles';
import Table from '@material-ui/core/Table';
import TableBody from '@material-ui/core/TableBody';
import Paper from '@material-ui/core/Paper';
import LinearProgress from '@material-ui/core/LinearProgress';
import DefaultTooltip from '@material-ui/core/Tooltip';
import Box from '@material-ui/core/Box';
import TableCell from '@material-ui/core/TableCell';

import StyledTableRow from './Row';
import IconPowerSettings from '../Icon/IconPowerSettings';
import IconPowerSettingsDisable from '../Icon/IconPowerSettingsDisable';
import StyledCheckbox from './Checkbox';
import IconDelete from '../Icon/IconDelete';
import IconLibraryAdd from '../Icon/IconLibraryAdd';
import ListingHeader from './Header';
import TABLE_COLUMN_TYPES from './ColumnTypes';
import PaginationActions from './PaginationActions';
import StyledPagination from './Pagination';
import Tooltip from '../Tooltip';

const loadingIndicatorHeight = 3;

const haveSameIds = (a, b) => a.id === b.id;

const BodyTableCell = withStyles({
  root: {
    fontSize: 13,
    lineHeight: 1.4,
    padding: '3px 4px',
  },
})(TableCell);

const styles = () => ({
  paper: {
    width: '100%',
    height: '100%',
    display: 'flex',
    flexDirection: 'column',
    boxShadow: 'none',
    background: 'none',
  },
  table: {
    boxShadow:
      '0px 1px 3px 0px rgba(0,0,0,0.2), 0px 1px 1px 0px rgba(0,0,0,0.14), 0px 2px 1px -1px rgba(0,0,0,0.12)',
  },
  rowDisabled: {
    backgroundColor: 'rgba(0, 0, 0, 0.07) !important',
  },
  loadingIndicator: {
    width: '100%',
    height: loadingIndicatorHeight,
  },
});

function cumulativeOffset(element) {
  if (!element || !element.offsetParent) {
    return 0;
  }

  return cumulativeOffset(element.offsetParent) + element.offsetTop;
}

class Listing extends React.Component {
  state = {
    tableTopOffset: 0,
  };

  tableBodyRef = React.createRef();

  componentDidMount() {
    const ro = new ResizeObserver(() => {
      this.setState({
        tableTopOffset: cumulativeOffset(this.tableBodyRef.current),
      });
    });

    ro.observe(this.tableBodyRef.current);
  }

  selectedRowsInclude = (row) => {
    const { selectedRows } = this.props;

    return !!selectedRows.find((includedRow) => haveSameIds(includedRow, row));
  };

  handleRequestSort = (_, property) => {
    const { onSort, sorto, sortf } = this.props;
    const isDesc = sortf === property && sorto === 'desc';

    onSort({
      order: isDesc ? 'asc' : 'desc',
      orderBy: property,
    });
  };

  selectAllRows = (event) => {
    const { onSelectRows, tableData } = this.props;
    if (event.target.checked) {
      onSelectRows(tableData);
      return;
    }

    onSelectRows([]);
  };

  selectRow = (event, row) => {
    event.preventDefault();
    event.stopPropagation();
    const { onSelectRows, selectedRows } = this.props;

    if (this.selectedRowsInclude(row)) {
      onSelectRows(selectedRows.filter((entity) => !haveSameIds(entity, row)));
      return;
    }
    onSelectRows([...selectedRows, row]);
  };

  rowHovered = (id, value) => {
    this.setState({
      hovered: value ? id : null,
    });
  };

  isSelected = (row) => {
    return this.selectedRowsInclude(row);
  };

  getColumnCell = ({ row, column }) => {
    const {
      onEnable,
      onDisable,
      onDuplicate,
      onDelete,
      labelEnableDisable,
      labelDuplicate,
      labelDelete,
    } = this.props;

    const { hovered } = this.state;

    const cellByColumnType = {
      [TABLE_COLUMN_TYPES.number]: () => (
        <BodyTableCell align="left">{row[column.id] || ''}</BodyTableCell>
      ),
      [TABLE_COLUMN_TYPES.string]: () => (
        <BodyTableCell key={column.id} align="left">
          {column.image && (
            <img
              alt=""
              src={row.iconPath}
              style={{
                maxWidth: 21,
                display: 'inline-block',
                verticalAlign: 'middle',
                marginRight: 5,
              }}
            />
          )}
          {row[column.id] || ''}
        </BodyTableCell>
      ),
      [TABLE_COLUMN_TYPES.toggler]: () => (
        <BodyTableCell align="left">
          {row[column.id] ? (
            <Tooltip
              label={labelEnableDisable}
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onDisable([row]);
              }}
            >
              <IconPowerSettings
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  onDisable([row]);
                }}
              />
            </Tooltip>
          ) : (
            <Tooltip
              label={labelEnableDisable}
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onEnable([row]);
              }}
            >
              <IconPowerSettingsDisable
                active
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  onEnable([row]);
                }}
              />
            </Tooltip>
          )}
        </BodyTableCell>
      ),
      [TABLE_COLUMN_TYPES.widthVariation]: () => (
        <BodyTableCell
          key={column.id}
          align="left"
          colSpan={this.isSelected(row) ? 1 : 5}
          style={{
            maxWidth: '145px',
            textOverflow: 'ellipsis',
            overflow: 'hidden',
          }}
        >
          <DefaultTooltip
            title={`${row[column.id]} (${row[column.subValue]})`}
            placement="top"
          >
            <span>{`${row[column.id]} (${row[column.subValue]})`}</span>
          </DefaultTooltip>
        </BodyTableCell>
      ),
      [TABLE_COLUMN_TYPES.multicolumn]: () => (
        <BodyTableCell key={column.id} align="left">
          {column.columns.map((subColumn) => (
            <>
              {`${subColumn.label} ${row[subColumn.id]}`}
              {subColumn.type === 'percentage' ? '%' : null}
              {'   '}
            </>
          ))}
        </BodyTableCell>
      ),
      [TABLE_COLUMN_TYPES.hoverActions]: () => (
        <BodyTableCell
          align="right"
          key={column.id}
          style={{
            width: 90,
            position: 'relative',
          }}
        >
          {hovered === row.id ? (
            <Box
              flexDirection="row"
              display="flex"
              style={{
                marginRight: -4,
                position: 'absolute',
                top: 3,
                right: 0,
              }}
            >
              <Box>
                <Tooltip
                  label={labelDelete}
                  onClick={() => {
                    onDelete([row]);
                  }}
                >
                  <IconDelete
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      onDelete([row]);
                    }}
                  />
                </Tooltip>
              </Box>
              <Box>
                <Tooltip
                  label={labelDuplicate}
                  onClick={() => {
                    onDuplicate([row]);
                  }}
                >
                  <IconLibraryAdd
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      onDuplicate([row]);
                    }}
                  />
                </Tooltip>
              </Box>
            </Box>
          ) : (
            ' '
          )}
        </BodyTableCell>
      ),
      [TABLE_COLUMN_TYPES.component]: () => {
        const Component = column.Component({
          row,
          isRowSelected: this.isSelected(row),
        });

        return (
          Component && (
            <BodyTableCell align="left" key={column.id}>
              {Component}
            </BodyTableCell>
          )
        );
      },
    };

    return cellByColumnType[column.type]();
  };

  render() {
    const {
      columnConfiguration,
      tableData,
      onPaginate,
      onPaginationLimitChanged,
      labelDisplayedRows,
      labelRowsPerPage,
      limit,
      checkable,
      currentPage,
      classes,
      totalRows,
      onRowClick = () => {},
      selectedRows,
      grayRowCondition,
      emptyDataMessage,
      loadingDataMessage,
      ariaLabel,
      paginated = true,
      loading,
    } = this.props;
    const { sorto, sortf } = this.props;

    const emptyRows = limit - Math.min(limit, totalRows - currentPage * limit);

    const tableMaxHeight = () => {
      const { tableTopOffset } = this.state;

      return `calc(100vh - ${tableTopOffset}px - 25px)`;
    };

    return (
      <>
        {loading && <LinearProgress className={classes.loadingIndicator} />}
        {!loading && <div className={classes.loadingIndicator} />}
        <Paper className={classes.paper}>
          {paginated ? (
            <StyledPagination
              rowsPerPageOptions={[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]}
              labelDisplayedRows={labelDisplayedRows}
              labelRowsPerPage={labelRowsPerPage}
              colSpan={3}
              count={totalRows}
              rowsPerPage={limit}
              page={currentPage}
              style={{
                display: 'flex',
                flexDirection: 'row-reverse',
                padding: 0,
                background: '#fff',
              }}
              SelectProps={{
                native: true,
              }}
              onChangePage={onPaginate}
              onChangeRowsPerPage={onPaginationLimitChanged}
              ActionsComponent={PaginationActions}
            />
          ) : null}
          <div
            style={{
              overflow: 'visible',
              maxHeight: tableMaxHeight(),
            }}
          >
            <Table
              className={classes.table}
              aria-label={ariaLabel}
              size="small"
              stickyHeader
            >
              <ListingHeader
                numSelected={selectedRows.length}
                order={sorto}
                checkable={checkable}
                orderBy={sortf}
                onSelectAllClick={this.selectAllRows}
                onRequestSort={this.handleRequestSort}
                rowCount={limit - emptyRows}
                headRows={columnConfiguration}
              />

              <TableBody
                ref={this.tableBodyRef}
                onMouseLeave={this.rowHovered.bind(this, '', false)}
                style={{
                  position: 'relative',
                }}
              >
                {tableData.map((row) => {
                  const isRowSelected = this.isSelected(row);

                  return (
                    <StyledTableRow
                      hover
                      tabIndex={-1}
                      key={row.id}
                      onMouseEnter={this.rowHovered.bind(this, row.id, true)}
                      className={clsx({
                        [classes.rowDisabled]: grayRowCondition(row),
                      })}
                      onClick={() => {
                        onRowClick(row);
                      }}
                    >
                      {checkable ? (
                        <BodyTableCell
                          align="left"
                          onClick={(event) => this.selectRow(event, row)}
                          padding="checkbox"
                        >
                          <StyledCheckbox
                            checked={isRowSelected}
                            inputProps={{
                              'aria-label': `Select row ${row.id}`,
                            }}
                            color="primary"
                          />
                        </BodyTableCell>
                      ) : null}

                      {columnConfiguration.map((column) =>
                        this.getColumnCell({ column, row }),
                      )}
                    </StyledTableRow>
                  );
                })}
                {tableData.length < 1 && (
                  <StyledTableRow tabIndex={-1}>
                    <BodyTableCell colSpan={6} align="center">
                      {loading ? loadingDataMessage : emptyDataMessage}
                    </BodyTableCell>
                  </StyledTableRow>
                )}
              </TableBody>
            </Table>
          </div>
        </Paper>
      </>
    );
  }
}

Listing.defaultProps = {
  ariaLabel: '',
  checkable: false,
  emptyDataMessage: 'No results found',
  grayRowCondition: () => false,
  labelDelete: 'Delete',
  labelDisplayedRows: ({ from, to, count }) => `${from}-${to} of ${count}`,
  labelDuplicate: 'Duplicate',
  labelEnableDisable: 'Enable / Disable',
  labelRowsPerPage: 'Rows per page',
  loading: false,
  loadingDataMessage: 'Loading data',
  onEnable: () => undefined,
  onDelete: () => undefined,
  onDisable: () => undefined,
  onDuplicate: () => undefined,
  onPaginate: () => undefined,
  onPaginationLimitChanged: () => undefined,
  onRowClick: () => undefined,
  onSelectRows: () => {},
  onSort: () => undefined,
  paginated: true,
  selectedRows: [],
  sorto: undefined,
  sortf: undefined,
};

const anyObject = PropTypes.objectOf(
  PropTypes.oneOfType([PropTypes.bool, PropTypes.string, PropTypes.number]),
);
const anyArray = PropTypes.arrayOf(anyObject);

Listing.propTypes = {
  ariaLabel: PropTypes.string,
  checkable: PropTypes.bool,
  classes: anyObject.isRequired,
  currentPage: PropTypes.number.isRequired,
  columnConfiguration: anyArray.isRequired,
  emptyDataMessage: PropTypes.string,
  grayRowCondition: PropTypes.func,
  labelDelete: PropTypes.string,
  labelDisplayedRows: PropTypes.func,
  labelDuplicate: PropTypes.string,
  labelEnableDisable: PropTypes.string,
  labelRowsPerPage: PropTypes.string,
  limit: PropTypes.number.isRequired,
  loading: PropTypes.bool,
  loadingDataMessage: PropTypes.string,
  onDelete: PropTypes.func,
  onDisable: PropTypes.func,
  onDuplicate: PropTypes.func,
  onEnable: PropTypes.func,
  onPaginate: PropTypes.func,
  onPaginationLimitChanged: PropTypes.func,
  onRowClick: PropTypes.func,
  onSelectRows: PropTypes.func,
  onSort: PropTypes.func,
  paginated: PropTypes.bool,
  selectedRows: anyArray,
  sorto: PropTypes.string,
  sortf: PropTypes.string,
  tableData: anyArray.isRequired,
  totalRows: PropTypes.number.isRequired,
};

export default withStyles(styles, { withTheme: true })(Listing);
