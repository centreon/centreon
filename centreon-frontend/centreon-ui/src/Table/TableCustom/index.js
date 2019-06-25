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

const styles = theme => ({
  root: {
    width: "100%"
  },
  paper: {
    width: "100%",
    marginBottom: theme.spacing(2)
  },
  table: {
    minWidth: 750
  },
  tableWrapper: {
    overflowX: "auto"
  }
});

class TableCustom extends Component {
  state = {
    order: "",
    orderBy: "",
    selected: []
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
      this.setState(
        {
          selected: newSelecteds
        },
        () => {
          onTableSelectionChanged(newSelecteds);
        }
      );
      return;
    }
    this.setState(
      {
        selected: []
      },
      () => {
        onTableSelectionChanged([]);
      }
    );
  };

  handleClick = (event, name) => {
    event.preventDefault();
    event.stopPropagation();
    const { selected } = this.state;
    const { onTableSelectionChanged } = this.props;
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
    this.setState(
      {
        selected: newSelected
      },
      () => {
        onTableSelectionChanged(newSelected);
      }
    );
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
      onToggle,
      onRowClick
    } = this.props;
    const { order, orderBy, selected, hovered } = this.state;

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
                                      onToggle([row.id]);
                                    }}
                                    active={true}
                                  />
                                ) : (
                                  <IconPowerSettingsDisable
                                    active={true}
                                    label="Disable"
                                    onClick={onToggle}
                                  />
                                )}
                              </StyledTableCell2>
                            );
                            break;
                          case TABLE_COLUMN_TYPES.hoverActions:
                            return (
                              <StyledTableCell2 style={{paddingTop: 0, paddingBottom: 0}}>
                                {hovered == row.id ? (
                                  <React.Fragment>
                                    <IconDelete
                                      customStyle={{
                                        color: "#707070",
                                        fontSize: 20,
                                        position: 'absolute',
                                        left: 15,
                                        top: 5,
                                      }}
                                      onClick={onDelete}
                                    />
                                    <IconLibraryAdd
                                      customStyle={{
                                        color: "#707070",
                                        marginLeft: "14px",
                                        fontSize: 20,
                                        position: 'absolute',
                                        left: 30,
                                        top: 5,
                                      }}
                                      onClick={onDuplicate}
                                    />
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
                {emptyRows > 0 && (
                  <StyledTableRow>
                    <StyledTableCell2 align="left" colSpan={6} />
                  </StyledTableRow>
                )}
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
