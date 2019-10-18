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

const BodyTableCell = withStyles({
  root: {
    fontSize: 13,
    lineHeight: 1.4,
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
  if (!element.offsetParent) {
    return 0;
  }

  return cumulativeOffset(element.offsetParent) + element.offsetTop;
}

class TableCustom extends Component {
  state = {
    order: '',
    orderBy: '',
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
    const {
      onEntitiesSelected,
      onTableSelectionChanged,
      tableData,
      nameIdPaired,
      indicatorsEditor,
    } = this.props;
    if (event.target.checked) {
      const newSelecteds = indicatorsEditor
        ? tableData
        : nameIdPaired
        ? tableData.map((n) => `${n.id}:${n.name}`)
        : tableData.map((n) => n.id);
      onTableSelectionChanged(newSelecteds);
      onEntitiesSelected(tableData);
      return;
    }

    onTableSelectionChanged([]);
  };

  handleClick = (event, row, editing) => {
    event.preventDefault();
    event.stopPropagation();
    const {
      onEntitiesSelected,
      onTableSelectionChanged,
      selected,
      nameIdPaired,
      indicatorsEditor,
      tableData,
    } = this.props;
    const value = indicatorsEditor
      ? row
      : nameIdPaired
      ? `${row.id}:${row.name}`
      : row.id;
    const selectedIndex = indicatorsEditor
      ? selected
          .map(({ object, type }) => {
            return `${object.id + type}Typed`;
          })
          .indexOf(`${value.object.id + value.type}Typed`)
      : selected.indexOf(value);
    let newSelected = [];

    if (editing) {
      newSelected = selected;
      newSelected[selectedIndex] = indicatorsEditor ? row : value;
    } else if (selectedIndex === -1) {
      newSelected = newSelected.concat(
        selected,
        indicatorsEditor ? row : value,
      );
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

    onEntitiesSelected(tableData.filter(({ id }) => newSelected.includes(id)));
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
      selected,
      enabledColumn,
      nameIdPaired,
      indicatorsEditor,
      emptyDataMessage,
      loadingDataMessage,
      ariaLabel,
      impacts,
      paginated = true,
      loading,
    } = this.props;
    const { order, orderBy, hovered } = this.state;

    const isSelected = (value) => {
      for (let i = 0; i < selected.length; i++) {
        if (indicatorsEditor) {
          if (
            selected[i].object.id === value.object.id &&
            selected[i].type === value.type
          ) {
            return {
              bool: true,
              obj: selected[i],
            };
          }
        } else if (selected[i] === value) {
          return {
            bool: true,
            obj: selected[i],
          };
        }
      }
      return {
        bool: false,
        obj: null,
      };
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
              overflow: 'auto',
              maxHeight: tableMaxHeight(),
            }}
          >
            <Table
              className={classes.table}
              aria-label={ariaLabel}
              size="small"
              stickyHeader
              style={
                indicatorsEditor
                  ? {
                      overflow: 'initial',
                    }
                  : {}
              }
            >
              <EnhancedTableHead
                numSelected={selected ? selected.length : 0}
                order={order}
                checkable={checkable}
                orderBy={orderBy}
                onSelectAllClick={this.handleSelectAllClick}
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
                  const isItemSelected = isSelected(
                    indicatorsEditor
                      ? row
                      : nameIdPaired
                      ? `${row.id}:${row.name}`
                      : row.id,
                  );
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
                        onRowClick(row.id);
                      }}
                    >
                      {checkable ? (
                        <BodyTableCell
                          align="left"
                          onClick={(event) => this.handleClick(event, row)}
                          padding="checkbox"
                          style={
                            indicatorsEditor
                              ? {
                                  padding: '3px 4px',
                                }
                              : {}
                          }
                        >
                          <StyledCheckbox
                            checked={isItemSelected.bool}
                            color="primary"
                          />
                        </BodyTableCell>
                      ) : null}

                      {columnConfiguration.map((column) => {
                        switch (column.type) {
                          case TABLE_COLUMN_TYPES.number:
                            return (
                              <BodyTableCell
                                align="left"
                                style={
                                  indicatorsEditor
                                    ? {
                                        padding: '3px 4px',
                                      }
                                    : {}
                                }
                              >
                                {row[column.id] || ''}
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.string:
                            return (
                              <BodyTableCell
                                key={column.id}
                                align="left"
                                style={
                                  indicatorsEditor
                                    ? {
                                        padding: '3px 4px',
                                      }
                                    : {}
                                }
                              >
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
                              <BodyTableCell
                                align="left"
                                style={
                                  indicatorsEditor
                                    ? {
                                        padding: '5px 4px',
                                      }
                                    : {}
                                }
                              >
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
                              <BodyTableCell
                                align="left"
                                style={
                                  indicatorsEditor
                                    ? {
                                        padding: '3px 4px',
                                      }
                                    : {}
                                }
                              >
                                {row[column.id] ? (
                                  <Tooltip
                                    label="Enable/Disable"
                                    onClick={(e) => {
                                      e.preventDefault();
                                      e.stopPropagation();
                                      onDisable([row.id]);
                                    }}
                                  >
                                    <IconPowerSettings
                                      label="Disable"
                                      onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        onDisable([row.id]);
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
                                    label="Enable/Disable"
                                    onClick={(e) => {
                                      e.preventDefault();
                                      e.stopPropagation();
                                      onEnable([row.id]);
                                    }}
                                  >
                                    <IconPowerSettingsDisable
                                      active
                                      label="Disable"
                                      onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        onEnable([row.id]);
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
                              <BodyTableCell
                                align="left"
                                style={
                                  indicatorsEditor
                                    ? {
                                        padding: '3px 4px',
                                      }
                                    : {}
                                }
                              >
                                <InputFieldTableCell />
                              </BodyTableCell>
                            );
                          case TABLE_COLUMN_TYPES.select:
                            return (
                              <BodyTableCell
                                align="left"
                                style={
                                  indicatorsEditor
                                    ? {
                                        padding: '3px 4px',
                                      }
                                    : {}
                                }
                              >
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
                                colSpan={isItemSelected.bool ? 1 : 5}
                                style={
                                  indicatorsEditor
                                    ? {
                                        padding: '3px 4px',
                                        maxWidth: '155px',
                                      }
                                    : {}
                                }
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
                              <BodyTableCell
                                key={column.id}
                                align="left"
                                style={
                                  indicatorsEditor
                                    ? {
                                        padding: '3px 4px',
                                      }
                                    : {}
                                }
                              >
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
                                      marginRight: -10,
                                      position: 'absolute',
                                      top: 6,
                                      right: 0,
                                    }}
                                  >
                                    <Box>
                                      <Tooltip
                                        label="Delete"
                                        onClick={() => {
                                          onDelete([row.id]);
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
                                            onDelete([row.id]);
                                          }}
                                        />
                                      </Tooltip>
                                    </Box>
                                    <Box>
                                      <Tooltip
                                        label="Duplicate"
                                        onClick={() => {
                                          onDuplicate([row.id]);
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
                                            onDuplicate([row.id]);
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
                          selected={isItemSelected}
                          onImpactEdit={this.handleClick}
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
  onRowClick: () => {},
  labelDisplayedRows: ({ from, to, count }) => `${from}-${to} of ${count}`,
  labelRowsPerPage: 'Rows per page',
  onEntitiesSelected: () => {},
  onTableSelectionChanged: () => {},
  nameIdPaired: false,
  indicatorsEditor: false,
  emptyDataMessage: 'No results found',
  loadingDataMessage: 'Loading data',
  loading: false,
  paginated: true,
  impacts: [],
};

const anyObject = PropTypes.objectOf(
  PropTypes.oneOfType([PropTypes.bool, PropTypes.string, PropTypes.number]),
);
const anyArray = PropTypes.arrayOf(anyObject);

TableCustom.propTypes = {
  ariaLabel: PropTypes.string,
  classes: anyObject.isRequired,
  onSort: PropTypes.func.isRequired,
  onEntitiesSelected: PropTypes.func,
  onTableSelectionChanged: PropTypes.func,
  columnConfiguration: anyArray.isRequired,
  tableData: anyArray.isRequired,
  onDelete: PropTypes.func.isRequired,
  onPaginate: PropTypes.func.isRequired,
  onDuplicate: PropTypes.func.isRequired,
  onPaginationLimitChanged: PropTypes.func.isRequired,
  labelDisplayedRows: PropTypes.func,
  labelRowsPerPage: PropTypes.string,
  limit: PropTypes.number.isRequired,
  checkable: PropTypes.bool.isRequired,
  currentPage: PropTypes.number.isRequired,
  totalRows: PropTypes.number.isRequired,
  onEnable: PropTypes.func.isRequired,
  onDisable: PropTypes.func.isRequired,
  onRowClick: PropTypes.func,
  selected: anyArray.isRequired,
  enabledColumn: PropTypes.string,
  nameIdPaired: PropTypes.bool,
  indicatorsEditor: PropTypes.bool,
  emptyDataMessage: PropTypes.string,
  loadingDataMessage: PropTypes.string,
  loading: PropTypes.bool,
  paginated: PropTypes.bool,
  impacts: anyArray,
};

export default withStyles(styles, { withTheme: true })(TableCustom);
