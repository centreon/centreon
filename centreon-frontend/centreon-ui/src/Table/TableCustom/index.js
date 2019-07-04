import React, { Component } from "react";
import PropTypes from "prop-types";
import { withStyles } from "@material-ui/core/styles";
import Table from "@material-ui/core/Table";
import TableBody from "@material-ui/core/TableBody";
import StyledTableRow from "./StyledTableRow";
import Paper from "@material-ui/core/Paper";
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

const styles = theme => ({
  root: {
    width: "100%"
  },
  paper: {
    width: "100%",
    marginBottom: theme.spacing(2)
  },
  tableWrapper: {
    overflowX: "auto"
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
    const { onTableSelectionChanged, tableData } = this.props;
    if (event.target.checked) {
      const newSelecteds = tableData.map(n => n.id);
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
      selected
    } = this.props;
    const { order, orderBy, hovered } = this.state;

    const isSelected = name => selected.indexOf(name) !== -1;

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
              <TableBody onMouseLeave={this.rowHovered.bind(this, "", false)}>
                {tableData.map(row => {
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
                    >
                      {checkable ? (
                        <StyledTableCell2
                          align="left"
                          onClick={event => this.handleClick(event, row.id)}
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
                            break;
                          case TABLE_COLUMN_TYPES.string:
                            return (
                              <TableCellCustom
                                align="left"
                                className={classes.tableCellCustom}
                              >
                                {row[column.id] || ""}
                              </TableCellCustom>
                            );
                            break;
                          case TABLE_COLUMN_TYPES.toggler:
                            return (
                              <StyledTableCell2 align="left">
                                {row[column.id] ? (
                                  <IconPowerSettings
                                    onClick={() => {
                                      onDisable([row.id]);
                                    }}
                                    active={true}
                                    customStyle={{
                                      fontSize: 19,
                                      boxSizing: "border-box",
                                      marginTop: 2
                                    }}
                                  />
                                ) : (
                                  <IconPowerSettingsDisable
                                    active={true}
                                    label="Disable"
                                    onClick={() => {
                                      onEnable([row.id]);
                                    }}
                                    customStyle={{
                                      fontSize: 18,
                                      boxSizing: "border-box",
                                      marginTop: 2
                                    }}
                                  />
                                )}
                              </StyledTableCell2>
                            );
                            break;
                          case TABLE_COLUMN_TYPES.multicolumn:
                            return (
                              <TableCellCustom
                                align="left"
                                className={classes.tableCellCustom}
                              >
                                {column.columns.map(subColumn => (
                                  <React.Fragment>
                                    {subColumn.label} {row[subColumn.id]}
                                    {subColumn.type == "percentage"
                                      ? "%"
                                      : null}
                                    {"   "}
                                  </React.Fragment>
                                ))}
                              </TableCellCustom>
                            );
                            break;
                          case TABLE_COLUMN_TYPES.hoverActions:
                            return (
                              <StyledTableCell2
                                style={{
                                  paddingTop: 0,
                                  paddingBottom: 0,
                                  minWidth: 94,
                                  boxSizing: "border-box"
                                }}
                              >
                                {hovered == row.id ? (
                                  <React.Fragment>
                                    <Tooltip
                                      label="More action"
                                      position={{ left: "50px" }}
                                      customStyle={{
                                        position: "absolute",
                                        left: 0,
                                        top: 0,
                                        padding: "3px 5px"
                                      }}
                                    >
                                      <IconDelete
                                        customStyle={{
                                          color: "#707070",
                                          fontSize: 21
                                        }}
                                        onClick={() => {
                                          onDelete([row.id]);
                                        }}
                                      />
                                    </Tooltip>
                                    <Tooltip
                                      label="More action"
                                      position={{ left: "50px" }}
                                      customStyle={{
                                        position: "absolute",
                                        left: 35,
                                        top: 0,
                                        padding: "3px 5px"
                                      }}
                                    >
                                      <IconLibraryAdd
                                        customStyle={{
                                          color: "#707070",
                                          fontSize: 20
                                        }}
                                        onClick={() => {
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
                            break;
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
            rowsPerPageOptions={[5, 10, 25, 50, 100]}
            colSpan={3}
            count={totalRows}
            rowsPerPage={limit}
            page={currentPage}
            style={{ display: "flex", flexDirection: "row-reverse" }}
            SelectProps={{
              native: true
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

TableCustom.propTypes = {
  classes: PropTypes.object.isRequired
};

export default withStyles(styles, { withTheme: true })(TableCustom);
