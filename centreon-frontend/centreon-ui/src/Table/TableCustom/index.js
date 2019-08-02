/* eslint-disable react/forbid-prop-types */
/* eslint-disable react/jsx-one-expression-per-line */
/* eslint-disable react/jsx-no-bind */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React, { Component } from "react";
import PropTypes from "prop-types";
import { withStyles } from "@material-ui/core/styles";
import Table from "@material-ui/core/Table";
import TableBody from "@material-ui/core/TableBody";
import Paper from "@material-ui/core/Paper";
import IconButton from "@material-ui/core/IconButton";
import StyledTableRow from "./StyledTableRow";
import IconPowerSettings from "../../MaterialComponents/Icons/IconPowerSettings";
import IconPowerSettingsDisable from "../../MaterialComponents/Icons/IconPowerSettingsDisable";
import StyledCheckbox from "./StyledCheckbox";
import IconDelete from "../../MaterialComponents/Icons/IconDelete";
import IconLibraryAdd from "../../MaterialComponents/Icons/IconLibraryAdd";
import EnhancedTableHead from "./EnhancedTableHead";
import TABLE_COLUMN_TYPES from "../ColumnTypes";
import TablePaginationActions from "./TablePaginationActions";
import StyledTableCell2 from "./StyledTableCell2";
import TableCellCustom from "./TableCellCustom";
import StyledPagination from "./StyledPagination";
import Tooltip from "../../MaterialComponents/Tooltip";
import InputFieldSelectTableCell from "../../InputField/InputFieldSelectTableCell";
import InputFieldTableCell from "../../InputField/InputFieldTableCell";

const styles = () => ({
  root: {
    width: "100%",
    display: "flex",
    height: "calc(100vh - 209px)"
  },
  paper: {
    width: "100%",
    display: "flex",
    flexDirection: "column",
    boxShadow: "none"
  },
  tableWrapper: {
    overflow: "auto",
    boxShadow:
      "0px 1px 3px 0px rgba(0,0,0,0.2), 0px 1px 1px 0px rgba(0,0,0,0.14), 0px 2px 1px -1px rgba(0,0,0,0.12)"
  },
  rowDisabled: {
    backgroundColor: "rgba(0, 0, 0, 0.07) !important"
  }
});

class TableCustom extends Component {
  state = {
    order: "",
    orderBy: ""
  };

  handleRequestSort = (event, property) => {
    const { onSort } = this.props;
    const { orderBy, order } = this.state;
    const isDesc = orderBy === property && order === "desc";
    this.setState(
      {
        order: isDesc ? "asc" : "desc",
        orderBy: property
      },
      () => {
        onSort({
          order: isDesc ? "asc" : "desc",
          orderBy: property
        });
      }
    );
  };

  handleSelectAllClick = event => {
    const { onTableSelectionChanged, tableData, nameIdPaired } = this.props;
    if (event.target.checked) {
      const newSelecteds = nameIdPaired
        ? tableData.map(n => `${n.id}:${n.name}`)
        : tableData.map(n => n.id);
      onTableSelectionChanged(newSelecteds);
      return;
    }

    onTableSelectionChanged([]);
  };

  handleClick = (event, object) => {
    event.preventDefault();
    event.stopPropagation();
    const { onTableSelectionChanged, selected, nameIdPaired } = this.props;
    const value = nameIdPaired ? `${object.id}:${object.name}` : object.id;
    const selectedIndex = selected.indexOf(value);
    let newSelected = [];

    if (selectedIndex === -1) {
      newSelected = newSelected.concat(selected, value);
    } else if (selectedIndex === 0) {
      newSelected = newSelected.concat(selected.slice(1));
    } else if (selectedIndex === selected.length - 1) {
      newSelected = newSelected.concat(selected.slice(0, -1));
    } else if (selectedIndex > 0) {
      newSelected = newSelected.concat(
        selected.slice(0, selectedIndex),
        selected.slice(selectedIndex + 1)
      );
    }
    onTableSelectionChanged(newSelected);
  };

  rowHovered = (id, value) => {
    this.setState({
      hovered: value ? id : null
    });
  };

  addConditionalRowBackground = (
    row,
    column,
    backgroundClass,
    attribute,
    classes
  ) => {
    return column
      ? {
          [attribute]: !row[column] ? classes[backgroundClass] : ""
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
      nameIdPaired,
      indicatorsEditor,
      emptyDataMessage
    } = this.props;
    const { order, orderBy, hovered } = this.state;

    const isSelected = name => {
      // eslint-disable-next-line
      for (let i = 0; i < selected.length; i++) {
        // eslint-disable-next-line
        if (selected[i] == name) {
          return true;
        }
      }
      return false;
    };

    const emptyRows = limit - Math.min(limit, totalRows - currentPage * limit);

    return (
      <div className={classes.root}>
        <Paper className={classes.paper}>
          <StyledPagination
            rowsPerPageOptions={[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]}
            colSpan={3}
            count={totalRows}
            rowsPerPage={limit}
            page={currentPage}
            style={{
              display: "flex",
              flexDirection: "row-reverse",
              padding: 0
            }}
            SelectProps={{
              native: true
            }}
            onChangePage={onPaginate}
            onChangeRowsPerPage={onPaginationLimitChanged}
            ActionsComponent={TablePaginationActions}
          />
          <div className={classes.tableWrapper}>
            <Table
              className={classes.table}
              aria-labelledby="tableTitle"
              size="small"
            >
              <EnhancedTableHead
                numSelected={selected ? selected.length : 0}
                order={order}
                checkable={checkable}
                orderBy={orderBy}
                onSelectAllClick={this.handleSelectAllClick}
                onRequestSort={this.handleRequestSort}
                rowCount={limit - emptyRows}
                className={classes.tableWrapper}
                headRows={columnConfiguration}
              />
              <TableBody onMouseLeave={this.rowHovered.bind(this, "", false)}>
                {tableData.map(row => {
                  const isItemSelected = isSelected(
                    nameIdPaired ? `${row.id}:${row.name}` : row.id
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
                        "rowDisabled",
                        "className",
                        classes
                      )}
                      onClick={() => {
                        onRowClick(row.id);
                      }}
                    >
                      {checkable ? (
                        <StyledTableCell2
                          align="left"
                          onClick={event => this.handleClick(event, row)}
                          className={classes.tableCell}
                          padding="checkbox"
                        >
                          <StyledCheckbox
                            checked={isItemSelected}
                            color="primary"
                          />
                        </StyledTableCell2>
                      ) : null}

                      {columnConfiguration.map(column => {
                        switch (column.type) {
                          case TABLE_COLUMN_TYPES.number:
                            return (
                              <TableCellCustom
                                align="left"
                                className={classes.tableCellCustom}
                              >
                                {row[column.id] || ""}
                              </TableCellCustom>
                            );
                          case TABLE_COLUMN_TYPES.string:
                            return (
                              <TableCellCustom
                                align="left"
                                className={classes.tableCellCustom}
                              >
                                {column.subkey
                                  ? row[column.subkey][column.id] || ""
                                  : row[column.id] || ""}
                              </TableCellCustom>
                            );
                          case TABLE_COLUMN_TYPES.boolean:
                            return (
                              <StyledTableCell2 align="left">
                                {row[column.id] ? (
                                  <IconButton
                                    style={{
                                      position: "absolute",
                                      top: -1,
                                      width: 31,
                                      height: 31,
                                      padding: 5
                                    }}
                                    disabled
                                  >
                                    <IconPowerSettings
                                      active
                                      customStyle={{
                                        fontSize: 18,
                                        boxSizing: "border-box",
                                        position: "relative",
                                        top: -2
                                      }}
                                    />
                                  </IconButton>
                                ) : (
                                  <IconButton
                                    style={{
                                      position: "absolute",
                                      top: -1,
                                      width: 31,
                                      height: 31,
                                      padding: 5
                                    }}
                                    disabled
                                  >
                                    <IconPowerSettingsDisable
                                      active
                                      customStyle={{
                                        fontSize: 18,
                                        boxSizing: "border-box",
                                        position: "relative",
                                        top: -2
                                      }}
                                    />
                                  </IconButton>
                                )}
                              </StyledTableCell2>
                            );
                          case TABLE_COLUMN_TYPES.toggler:
                            return (
                              <StyledTableCell2 align="left">
                                {row[column.id] ? (
                                  <Tooltip
                                    label="Enable/Disable"
                                    customStyle={{
                                      position: "absolute",
                                      top: -1,
                                      width: 31,
                                      height: 31
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
                                        boxSizing: "border-box",
                                        position: "relative",
                                        top: -2
                                      }}
                                    />
                                  </Tooltip>
                                ) : (
                                  <Tooltip
                                    label="Enable/Disable"
                                    customStyle={{
                                      position: "absolute",
                                      top: -1,
                                      width: 31,
                                      height: 31
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
                                        boxSizing: "border-box",
                                        position: "relative",
                                        top: -2
                                      }}
                                    />
                                  </Tooltip>
                                )}
                              </StyledTableCell2>
                            );
                          case TABLE_COLUMN_TYPES.input:
                            return (
                              <StyledTableCell2 align="left">
                                <InputFieldTableCell />
                              </StyledTableCell2>
                            );
                          case TABLE_COLUMN_TYPES.select:
                            return (
                              <StyledTableCell2 align="left">
                                <InputFieldSelectTableCell
                                  options={column.options}
                                  value={
                                    column.subkey
                                      ? row[column.subkey][column.id]
                                      : row[column.key]
                                  }
                                  active="active"
                                />
                              </StyledTableCell2>
                            );
                          case TABLE_COLUMN_TYPES.multicolumn:
                            return (
                              <TableCellCustom
                                align="left"
                                className={classes.tableCellCustom}
                              >
                                {column.columns.map(subColumn => (
                                  <React.Fragment>
                                    {subColumn.label} {row[subColumn.id]}
                                    {subColumn.type === "percentage"
                                      ? "%"
                                      : null}
                                    {"   "}
                                  </React.Fragment>
                                ))}
                              </TableCellCustom>
                            );
                          case TABLE_COLUMN_TYPES.hoverActions:
                            return (
                              <StyledTableCell2
                                style={{
                                  paddingTop: 0,
                                  paddingBottom: 0,
                                  width: 60,
                                  boxSizing: "border-box",
                                  padding: "0 !important"
                                }}
                              >
                                {hovered === row.id ? (
                                  <React.Fragment>
                                    <Tooltip
                                      label="Delete"
                                      position={{ left: "50px" }}
                                      customStyle={{
                                        position: "absolute",
                                        right: 28,
                                        top: 0,
                                        padding: "3px 5px"
                                      }}
                                    >
                                      <IconDelete
                                        customStyle={{
                                          color: "#707070",
                                          fontSize: 21
                                        }}
                                        onClick={(e) => {
                                          e.preventDefault();
                                          e.stopPropagation();
                                          onDelete([row.id]);
                                        }}
                                      />
                                    </Tooltip>
                                    <Tooltip
                                      label="Duplicate"
                                      position={{ left: "50px" }}
                                      customStyle={{
                                        position: "absolute",
                                        right: -5,
                                        top: 0,
                                        padding: "3px 5px"
                                      }}
                                    >
                                      <IconLibraryAdd
                                        customStyle={{
                                          color: "#707070",
                                          fontSize: 20
                                        }}
                                        onClick={(e) => {
                                          e.preventDefault();
                                          e.stopPropagation();
                                          onDuplicate([row.id]);
                                        }}
                                      />
                                    </Tooltip>
                                  </React.Fragment>
                                ) : (
                                  " "
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
                {tableData.length < 1 ? (
                  <StyledTableRow tabIndex={-1}>
                    <TableCellCustom
                      colSpan={6}
                      align="center"
                      className={classes.tableCellCustom}
                    >
                      {emptyDataMessage
                        ? emptyDataMessage
                        : "No results found."}
                    </TableCellCustom>
                  </StyledTableRow>
                ) : null}
              </TableBody>
            </Table>
          </div>
        </Paper>
      </div>
    );
  }
}

TableCustom.propTypes = {
  classes: PropTypes.object.isRequired
};

export default withStyles(styles, { withTheme: true })(TableCustom);
