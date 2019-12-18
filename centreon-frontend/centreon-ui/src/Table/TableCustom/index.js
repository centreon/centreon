/* eslint-disable no-plusplus */
/* eslint-disable no-nested-ternary */
/* eslint-disable react/jsx-no-bind */
/* eslint-disable react/jsx-filename-extension */

import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { withStyles } from '@material-ui/core/styles';
import Table from '@material-ui/core/Table';
import TableBody from '@material-ui/core/TableBody';
import Paper from '@material-ui/core/Paper';
import IconButton from '@material-ui/core/IconButton';
import LinearProgress from '@material-ui/core/LinearProgress';
import DefaultTooltip from '@material-ui/core/Tooltip';
import Box from '@material-ui/core/Box';
import TableCell from '@material-ui/core/TableCell';
import ResizeObserver from 'resize-observer-polyfill';
import StyledTableRow from './StyledTableRow';
import IconPowerSettings from '../../MaterialComponents/Icons/IconPowerSettings';
import IconPowerSettingsDisable from '../../MaterialComponents/Icons/IconPowerSettingsDisable';
import StyledCheckbox from './StyledCheckbox';
import IconDelete from '../../MaterialComponents/Icons/IconDelete';
import IconLibraryAdd from '../../MaterialComponents/Icons/IconLibraryAdd';
import EnhancedTableHead from './EnhancedTableHead';
import TABLE_COLUMN_TYPES from '../ColumnTypes';
import TablePaginationActions from './TablePaginationActions';
import StyledPagination from './StyledPagination';
import Tooltip from '../../MaterialComponents/Tooltip';
import InputFieldSelectTableCell from '../../InputField/InputFieldSelectTableCell';
import InputFieldTableCell from '../../InputField/InputFieldTableCell';
import IndicatorsEditor from './IndicatorsEditorRow';

const loadingIndicatorHeight = 3;

const deepEqual = (a, b) => a.id === b.id;

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

class TableCustom extends Component {
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

    return !!selectedRows.find((includedRow) => deepEqual(includedRow, row));
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
      onSelectRows(selectedRows.filter((entity) => !deepEqual(entity, row)));
      return;
    }
    onSelectRows([...selectedRows, row]);
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
      labelDisplayedRows,
      labelRowsPerPage,
      limit,
      checkable,
      currentPage,
      classes,
      totalRows,
      onEnable,
      onDisable,
      onRowClick = () => {},
      selectedRows,
      enabledColumn,
      indicatorsEditor,
      emptyDataMessage,
      loadingDataMessage,
      ariaLabel,
      impacts,
      paginated = true,
      loading,
    } = this.props;
    const { hovered } = this.state;
    const {
      sorto,
      sortf,
      labelEnableDisable,
      labelDuplicate,
      labelDelete,
    } = this.props;

    const isSelected = (row) => {
      return this.selectedRowsInclude(row);
    };

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
              ActionsComponent={TablePaginationActions}
            />
          ) : null}
          <div
            style={{
              overflow: indicatorsEditor ? 'visible' : 'auto',
              maxHeight: tableMaxHeight(),
            }}
          >
            <Table
              className={classes.table}
              aria-label={ariaLabel}
              size="small"
              stickyHeader
            >
              <EnhancedTableHead
                numSelected={selectedRows.length}
                order={sorto}
                checkable={checkable}
                orderBy={sortf}
                onSelectAllClick={this.selectAllRows}
                onRequestSort={this.handleRequestSort}
                rowCount={limit - emptyRows}
                headRows={columnConfiguration}
                indicatorsEditor={indicatorsEditor}
              />

              <TableBody
                ref={this.tableBodyRef}
                onMouseLeave={this.rowHovered.bind(this, '', false)}
                style={{
                  position: 'relative',
                }}
              >
                {tableData.map((row, index) => {
                  const isRowSelected = isSelected(row);

                  return (
                    <StyledTableRow
                      hover
                      tabIndex={-1}
                      key={row.id}
                      onMouseEnter={this.rowHovered.bind(this, row.id, true)}
                      {...this.addConditionalRowBackground(
                        row,
                        enabledColumn,
                        'rowDisabled',
                        'className',
                        classes,
                      )}
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

                      {columnConfiguration.map((column) => {
                        switch (column.type) {
                          case TABLE_COLUMN_TYPES.number:
                            return (
                              <BodyTableCell align="left">
                                {row[column.id] || ''}
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.string:
                            return (
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
                                {column.subkey
                                  ? row[column.subkey][column.id] || ''
                                  : row[column.id] || ''}
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.boolean:
                            return (
                              <BodyTableCell align="left">
                                {row[column.id] ? (
                                  <IconButton
                                    style={{
                                      position: 'absolute',
                                      top: -1,
                                      width: 31,
                                      height: 31,
                                      padding: 5,
                                    }}
                                    disabled
                                  >
                                    <IconPowerSettings
                                      active
                                      customStyle={{
                                        fontSize: 18,
                                        boxSizing: 'border-box',
                                        position: 'relative',
                                        top: -2,
                                      }}
                                    />
                                  </IconButton>
                                ) : (
                                  <IconButton
                                    style={{
                                      position: 'absolute',
                                      top: -1,
                                      width: 31,
                                      height: 31,
                                      padding: 5,
                                    }}
                                    disabled
                                  >
                                    <IconPowerSettingsDisable
                                      active
                                      customStyle={{
                                        fontSize: 18,
                                        boxSizing: 'border-box',
                                        position: 'relative',
                                        top: -2,
                                      }}
                                    />
                                  </IconButton>
                                )}
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.toggler:
                            return (
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
                                      label="Disable"
                                      onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        onDisable([row]);
                                      }}
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
                                    label={labelEnableDisable}
                                    onClick={(e) => {
                                      e.preventDefault();
                                      e.stopPropagation();
                                      onEnable([row]);
                                    }}
                                  >
                                    <IconPowerSettingsDisable
                                      active
                                      label="Disable"
                                      onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        onEnable([row]);
                                      }}
                                      customStyle={{
                                        fontSize: 18,
                                        boxSizing: 'border-box',
                                        position: 'relative',
                                        top: -2,
                                      }}
                                    />
                                  </Tooltip>
                                )}
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.input:
                            return (
                              <BodyTableCell align="left">
                                <InputFieldTableCell />
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.select:
                            return (
                              <BodyTableCell align="left">
                                <InputFieldSelectTableCell
                                  options={column.options}
                                  value={
                                    column.subkey
                                      ? row[column.subkey][column.id]
                                      : row[column.key]
                                  }
                                  active="active"
                                />
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.widthVariation:
                            return (
                              <BodyTableCell
                                key={column.id}
                                align="left"
                                colSpan={isRowSelected ? 1 : 5}
                                style={{
                                  maxWidth: '145px',
                                  textOverflow: 'ellipsis',
                                  overflow: 'hidden',
                                }}
                              >
                                <DefaultTooltip
                                  title={`${row[column.id]} (${
                                    row[column.subValue]
                                  })`}
                                  placement="top"
                                >
                                  <span>
                                    {`${row[column.id]} (${
                                      row[column.subValue]
                                    })`}
                                  </span>
                                </DefaultTooltip>
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.multicolumn:
                            return (
                              <BodyTableCell key={column.id} align="left">
                                {column.columns.map((subColumn) => (
                                  <>
                                    {`${subColumn.label} ${row[subColumn.id]}`}
                                    {subColumn.type === 'percentage'
                                      ? '%'
                                      : null}
                                    {'   '}
                                  </>
                                ))}
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.hoverActions:
                            return (
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
                                          customStyle={{
                                            color: '#707070',
                                            fontSize: 21,
                                          }}
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
                                          customStyle={{
                                            color: '#707070',
                                            fontSize: 20,
                                          }}
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
                            );
                          default:
                            return null;
                        }
                      })}
                      {indicatorsEditor ? (
                        <IndicatorsEditor
                          row={row}
                          index={index}
                          impacts={impacts}
                          selected={isRowSelected}
                          onImpactEdit={(updatedRow) => {
                            const { onSelectRows } = this.props;

                            const newSelection = selectedRows.map(
                              (selectedRow) =>
                                selectedRow.id === updatedRow.id
                                  ? updatedRow
                                  : selectedRow,
                            );

                            onSelectRows(newSelection);
                          }}
                        />
                      ) : null}
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

TableCustom.defaultProps = {
  enabledColumn: '',
  ariaLabel: '',
  dense: false,
  onRowClick: () => {},
  labelDisplayedRows: ({ from, to, count }) => `${from}-${to} of ${count}`,
  labelRowsPerPage: 'Rows per page',
  onSelectRows: () => {},
  indicatorsEditor: false,
  emptyDataMessage: 'No results found',
  loadingDataMessage: 'Loading data',
  loading: false,
  paginated: true,
  impacts: [],
  selectedRows: [],
  sorto: undefined,
  sortf: undefined,
  onEnable: () => undefined,
  onDisable: () => undefined,
  labelEnableDisable: 'Enable / Disable',
  labelDelete: 'Delete',
  labelDuplicate: 'Duplicate',
};

const anyObject = PropTypes.objectOf(
  PropTypes.oneOfType([PropTypes.bool, PropTypes.string, PropTypes.number]),
);
const anyArray = PropTypes.arrayOf(anyObject);

TableCustom.propTypes = {
  ariaLabel: PropTypes.string,
  classes: anyObject.isRequired,
  dense: PropTypes.bool,
  onSort: PropTypes.func.isRequired,
  onSelectRows: PropTypes.func,
  columnConfiguration: anyArray.isRequired,
  tableData: anyArray.isRequired,
  onDelete: PropTypes.func.isRequired,
  onPaginate: PropTypes.func.isRequired,
  onDuplicate: PropTypes.func.isRequired,
  onPaginationLimitChanged: PropTypes.func.isRequired,
  sorto: PropTypes.string,
  sortf: PropTypes.string,
  labelDisplayedRows: PropTypes.func,
  labelRowsPerPage: PropTypes.string,
  limit: PropTypes.number.isRequired,
  checkable: PropTypes.bool.isRequired,
  currentPage: PropTypes.number.isRequired,
  totalRows: PropTypes.number.isRequired,
  onEnable: PropTypes.func,
  onDisable: PropTypes.func,
  onRowClick: PropTypes.func,
  selectedRows: anyArray,
  enabledColumn: PropTypes.string,
  indicatorsEditor: PropTypes.bool,
  emptyDataMessage: PropTypes.string,
  loadingDataMessage: PropTypes.string,
  loading: PropTypes.bool,
  paginated: PropTypes.bool,
  impacts: anyArray,
  labelEnableDisable: PropTypes.string,
  labelDelete: PropTypes.string,
  labelDuplicate: PropTypes.string,
};

export default withStyles(styles, { withTheme: true })(TableCustom);
