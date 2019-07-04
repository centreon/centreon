import React from "react";
import PropTypes from "prop-types";
import { makeStyles, withStyles, useTheme } from "@material-ui/core/styles";
import Table from "@material-ui/core/Table";
import TableBody from "@material-ui/core/TableBody";
import TableCell from "@material-ui/core/TableCell";
import TableHead from "@material-ui/core/TableHead";
import TablePagination from "@material-ui/core/TablePagination";
import TableRow from "@material-ui/core/TableRow";
import TableSortLabel from "@material-ui/core/TableSortLabel";
import Paper from "@material-ui/core/Paper";
import IconButton from "@material-ui/core/IconButton";
import FirstPageIcon from "@material-ui/icons/FirstPage";
import KeyboardArrowLeft from "@material-ui/icons/KeyboardArrowLeft";
import KeyboardArrowRight from "@material-ui/icons/KeyboardArrowRight";
import LastPageIcon from "@material-ui/icons/LastPage";
import Checkbox from "@material-ui/core/Checkbox";
import IconPowerSettings from "../../MaterialComponents/Icons/IconPowerSettings";

function createData(name, activate, calculation, description) {
  return { name, activate, calculation, description };
}

const rows = [
  createData("Cupcake", 332, 3.7, 67, 4.3),
  createData("Donut", 332, 25.0, 51, 4.9),
  createData("Eclair", 332, 16.0, 24, 6.0),
  createData("Frozen yoghurt", 332, 6.0, 24, 4.0),
  createData("Gingerbread", 332, 16.0, 49, 3.9),
  createData("Honeycomb", 332, 3.2, 87, 6.5),
  createData("Ice cream sandwich", 332, 9.0, 37, 4.3),
  createData("Jelly Bean", 332, 0.0, 94, 0.0),
  createData("KitKat", 332, 26.0, 65, 7.0),
  createData("Lollipop", 332, 0.2, 98, 0.0),
  createData("Marshmallow", 332, 0, 81, 2.0),
  createData("Nougat", 332, 19.0, 9, 37.0),
  createData("Oreo", 332, 18.0, 63, 4.0)
];

function desc(a, b, orderBy) {
  if (b[orderBy] < a[orderBy]) {
    return -1;
  }
  if (b[orderBy] > a[orderBy]) {
    return 1;
  }
  return 0;
}

function stableSort(array, cmp) {
  const stabilizedThis = array.map((el, index) => [el, index]);
  stabilizedThis.sort((a, b) => {
    const order = cmp(a[0], b[0]);
    if (order !== 0) return order;
    return a[1] - b[1];
  });
  return stabilizedThis.map(el => el[0]);
}

function getSorting(order, orderBy) {
  return order === "desc"
    ? (a, b) => desc(a, b, orderBy)
    : (a, b) => -desc(a, b, orderBy);
}

const headRows = [
  { id: "name", numeric: false, disablePadding: true, label: "Name" },
  { id: "activate", numeric: true, disablePadding: false, label: "Activate" },
  {
    id: "calculation",
    numeric: true,
    disablePadding: false,
    label: "Calculation method"
  },
  {
    id: "description",
    numeric: true,
    disablePadding: false,
    label: "Description"
  }
];

function EnhancedTableHead(props) {
  const {
    onSelectAllClick,
    order,
    orderBy,
    numSelected,
    rowCount,
    onRequestSort
  } = props;
  const createSortHandler = property => event => {
    onRequestSort(event, property);
  };

  const StyledTableCell = withStyles({
    head: {
      backgroundColor: "#009fdf",
      color: "#fff",
      height: "24px",
      padding: "6px 24px 6px 16px",
      "&:hover": {
        color: "#fff"
      }
    },
    body: {
      fontSize: 12,
      textAlign: "left"
    }
  })(TableCell);

  const StyledTableSortLabel = withStyles({
    root: {
      color: "#fff !important"
    },
    icon: {
      color: "#fff !important"
    },
    active: {
      color: "#fff !important"
    }
  })(TableSortLabel);

  return (
    <TableHead>
      <TableRow>
        <StyledTableCell align="left" padding="checkbox">
          <StyledCheckbox
            indeterminate={numSelected > 0 && numSelected < rowCount}
            checked={numSelected === rowCount}
            onChange={onSelectAllClick}
          />
        </StyledTableCell>
        {headRows.map(row => (
          <StyledTableCell
            key={row.id}
            align={row.numeric ? "left" : ""}
            padding={row.disablePadding ? "none" : "default"}
            sortDirection={orderBy === row.id ? order : false}
          >
            <StyledTableSortLabel
              active={orderBy === row.id}
              direction={order}
              onClick={createSortHandler(row.id)}
              icon={{ color: "red" }}
            >
              {row.label}
            </StyledTableSortLabel>
          </StyledTableCell>
        ))}
      </TableRow>
    </TableHead>
  );
}

EnhancedTableHead.propTypes = {
  numSelected: PropTypes.number.isRequired,
  onRequestSort: PropTypes.func.isRequired,
  onSelectAllClick: PropTypes.func.isRequired,
  order: PropTypes.string.isRequired,
  orderBy: PropTypes.string.isRequired,
  rowCount: PropTypes.number.isRequired
};

const useStyles = makeStyles(theme => ({
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
}));

const StyledTableRow = withStyles({
  root: {
    "&:nth-of-type(odd)": {
      backgroundColor: "#e3f2fd"
    },
    "&:hover": {
      backgroundColor: "#cae6f1 !important"
    },
    cursor: "pointer"
  }
})(TableRow);

const StyledTableCell2 = withStyles({
  root: {
    padding: "3px 24px 3px 16px",
    fontSize: "13px"
  }
})(TableCell);

const StyledCheckbox = withStyles({
  root: {
    "&$checked": {
      color: "#232f39"
    }
  },
  checked: {}
})(Checkbox);

const StyledPagination = withStyles({
  toolbar: {
    height: "32px",
    minHeight: "auto"
  }
})(TablePagination);

function EnhancedTable() {
  const classes = useStyles();
  const [order, setOrder] = React.useState("asc");
  const [orderBy, setOrderBy] = React.useState("activate");
  const [selected, setSelected] = React.useState([]);
  const [page, setPage] = React.useState(0);
  const [rowsPerPage, setRowsPerPage] = React.useState(5);

  function handleRequestSort(event, property) {
    const isDesc = orderBy === property && order === "desc";
    setOrder(isDesc ? "asc" : "desc");
    setOrderBy(property);
  }

  function handleSelectAllClick(event) {
    if (event.target.checked) {
      const newSelecteds = rows.map(n => n.name);
      setSelected(newSelecteds);
      return;
    }
    setSelected([]);
  }

  function handleClick(event, name) {
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

    setSelected(newSelected);
  }

  function handleChangePage(event, newPage) {
    setPage(newPage);
  }

  function handleChangeRowsPerPage(event) {
    setRowsPerPage(+event.target.value);
  }

  const useStyles1 = makeStyles(theme => ({
    root: {
      flexShrink: 0,
      color: theme.palette.text.secondary,
      marginLeft: theme.spacing(2.5)
    }
  }));

  function TablePaginationActions(props) {
    const classes = useStyles1();
    const theme = useTheme();
    const { count, page, rowsPerPage, onChangePage } = props;

    function handleFirstPageButtonClick(event) {
      onChangePage(event, 0);
    }

    function handleBackButtonClick(event) {
      onChangePage(event, page - 1);
    }

    function handleNextButtonClick(event) {
      onChangePage(event, page + 1);
    }

    function handleLastPageButtonClick(event) {
      onChangePage(event, Math.max(0, Math.ceil(count / rowsPerPage) - 1));
    }

    return (
      <div className={classes.root}>
        <IconButton
          onClick={handleFirstPageButtonClick}
          disabled={page === 0}
          aria-label="First Page"
        >
          {theme.direction === "rtl" ? <LastPageIcon /> : <FirstPageIcon />}
        </IconButton>
        <IconButton
          onClick={handleBackButtonClick}
          disabled={page === 0}
          aria-label="Previous Page"
        >
          {theme.direction === "rtl" ? (
            <KeyboardArrowRight />
          ) : (
            <KeyboardArrowLeft />
          )}
        </IconButton>
        <IconButton
          onClick={handleNextButtonClick}
          disabled={page >= Math.ceil(count / rowsPerPage) - 1}
          aria-label="Next Page"
        >
          {theme.direction === "rtl" ? (
            <KeyboardArrowLeft />
          ) : (
            <KeyboardArrowRight />
          )}
        </IconButton>
        <IconButton
          onClick={handleLastPageButtonClick}
          disabled={page >= Math.ceil(count / rowsPerPage) - 1}
          aria-label="Last Page"
        >
          {theme.direction === "rtl" ? <FirstPageIcon /> : <LastPageIcon />}
        </IconButton>
      </div>
    );
  }

  TablePaginationActions.propTypes = {
    count: PropTypes.number.isRequired,
    onChangePage: PropTypes.func.isRequired,
    page: PropTypes.number.isRequired,
    rowsPerPage: PropTypes.number.isRequired
  };

  const isSelected = name => selected.indexOf(name) !== -1;

  const emptyRows =
    rowsPerPage - Math.min(rowsPerPage, rows.length - page * rowsPerPage);

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
              orderBy={orderBy}
              onSelectAllClick={handleSelectAllClick}
              onRequestSort={handleRequestSort}
              rowCount={rows.length}
              className={classes.tableWrapper}
            />
            <TableBody>
              {stableSort(rows, getSorting(order, orderBy))
                .slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage)
                .map(row => {
                  const isItemSelected = isSelected(row.name);
                  return (
                    <StyledTableRow
                      hover
                      onClick={event => handleClick(event, row.name)}
                      role="checkbox"
                      aria-checked={isItemSelected}
                      tabIndex={-1}
                      key={row.name}
                      selected={isItemSelected}
                    >
                      <StyledTableCell2
                        align="left"
                        className={classes.tableCell}
                        padding="checkbox"
                      >
                        <StyledCheckbox
                          checked={isItemSelected}
                          color="primary"
                        />
                      </StyledTableCell2>
                      <StyledTableCell2 align="left">
                        {row.name}
                      </StyledTableCell2>
                      <StyledTableCell2 align="left">
                        {row.activate}
                      </StyledTableCell2>
                      <StyledTableCell2 align="left">
                        {row.calculation}
                      </StyledTableCell2>
                      <StyledTableCell2 align="left">
                        {row.description}
                      </StyledTableCell2>
                    </StyledTableRow>
                  );
                })}
              {emptyRows > 0 && (
                <StyledTableRow style={{ height: 49 * emptyRows }}>
                  <StyledTableCell2 align="left" colSpan={6} />
                </StyledTableRow>
              )}
            </TableBody>
          </Table>
        </div>
        <StyledPagination
          rowsPerPageOptions={[5, 10, 25, 50, 100]}
          colSpan={3}
          count={rows.length}
          rowsPerPage={rowsPerPage}
          page={page}
          style={{ display: "flex", flexDirection: "row-reverse" }}
          SelectProps={{
            native: true
          }}
          onChangePage={handleChangePage}
          onChangeRowsPerPage={handleChangeRowsPerPage}
          ActionsComponent={TablePaginationActions}
        />
      </Paper>
    </div>
  );
}

export default EnhancedTable;
