import React, { useState, useRef, RefObject } from 'react';

import { equals, isNil } from 'ramda';

import { makeStyles, Theme } from '@material-ui/core/styles';
import {
  Table,
  TableBody,
  Paper,
  LinearProgress,
  TableRow,
  useTheme,
} from '@material-ui/core';

import useMemoComponent from '../utils/useMemoComponent';

import ListingHeader, { headerHeight } from './Header';
import ListingRow from './Row';
import PaginationActions from './PaginationActions';
import StyledPagination from './Pagination';
import ListingLoadingSkeleton from './Skeleton';
import useResizeObserver from './useResizeObserver';
import getCumulativeOffset from './getCumulativeOffset';
import DataCell from './Cell/DataCell';
import Cell from './Cell';
import Checkbox from './Checkbox';

const loadingIndicatorHeight = 3;

const useStyles = makeStyles<Theme>((theme) => ({
  container: {
    width: '100%',
    height: '100%',
    display: 'flex',
    flexDirection: 'column',
    background: 'none',
  },
  actionBar: {
    display: 'flex',
    alignItems: 'center',
  },
  actions: {
    padding: theme.spacing(1),
  },
  paginationElement: {
    marginLeft: 'auto',
    display: 'flex',
    flexDirection: 'row-reverse',
    padding: 0,
  },
  loadingIndicator: {
    width: '100%',
    height: loadingIndicatorHeight,
  },
  table: {
    position: 'relative',
    display: 'grid',
    alignItems: 'center',
  },
  tableBody: {
    position: 'relative',
    display: 'contents',
  },
  paper: {
    overflow: 'auto',
  },
  emptyDataRow: {
    display: 'contents',
  },
  emptyDataCell: {
    paddingLeft: theme.spacing(2),
  },
}));

type RowId = number | string;

export interface Props {
  checkable?: boolean;
  currentPage?: number;
  columnConfiguration;
  emptyDataMessage?: string;
  rowColorConditions?;
  labelRowsPerPage?: string;
  limit?: number;
  loading?: boolean;
  loadingDataMessage?: string;
  paginated?: boolean;
  selectedRows?;
  sorto?: 'asc' | 'desc';
  sortf?: string;
  tableData?;
  totalRows?;
  innerScrollDisabled?: boolean;
  expanded?: boolean;
  Actions?: JSX.Element;
  disableRowCheckCondition?;
  labelDisplayedRows?: (fromToCount) => string;
  onPaginate?: (event, value) => void;
  onPaginationLimitChanged?: (event) => void;
  onRowClick?: (row) => void;
  onSelectRows?: (rows) => void;
  onSort?: (sortParams) => void;
  getId?: (row) => RowId;
}

const Listing = ({
  limit = 10,
  columnConfiguration,
  tableData = [],
  currentPage = 0,
  totalRows = 0,
  checkable = false,
  emptyDataMessage = 'No results found',
  rowColorConditions = [],
  labelRowsPerPage = 'Rows per page',
  loading = false,
  paginated = true,
  selectedRows = [],
  sorto = undefined,
  sortf = undefined,
  innerScrollDisabled = false,
  Actions,
  disableRowCheckCondition = (): boolean => false,
  onPaginate = (): void => undefined,
  onPaginationLimitChanged = (): void => undefined,
  onRowClick = (): void => undefined,
  onSelectRows = (): void => undefined,
  onSort = (): void => undefined,
  labelDisplayedRows = ({ from, to, count }): string =>
    `${from}-${to} of ${count}`,
  getId = ({ id }) => id,
}: Props): JSX.Element => {
  const [tableTopOffset, setTableTopOffset] = useState(0);
  const [hoveredRowId, setHoveredRowId] = useState<RowId | null>(null);

  const containerRef = useRef<HTMLDivElement>();
  const actionBarRef = useRef<HTMLDivElement>();

  const classes = useStyles();

  const theme = useTheme();

  useResizeObserver({
    ref: containerRef,
    onResize: () => {
      setTableTopOffset(getCumulativeOffset(containerRef.current));
    },
  });

  const selectedRowsInclude = (row): boolean => {
    return !!selectedRows.find((includedRow) =>
      equals(getId(includedRow), getId(row)),
    );
  };

  const handleRequestSort = (_, property): void => {
    const isDesc = sortf === property && sorto === 'desc';

    onSort({
      order: isDesc ? 'asc' : 'desc',
      orderBy: property,
    });
  };

  const selectAllRows = (event): void => {
    if (
      event.target.checked &&
      event.target.getAttribute('data-indeterminate') === 'false'
    ) {
      onSelectRows(tableData);
      return;
    }

    onSelectRows([]);
  };

  const selectRow = (event, row): void => {
    event.preventDefault();
    event.stopPropagation();

    if (selectedRowsInclude(row)) {
      onSelectRows(
        selectedRows.filter((entity) => !equals(getId(entity), getId(row))),
      );
      return;
    }
    onSelectRows([...selectedRows, row]);
  };

  const hoverRow = (row): void => {
    if (equals(hoveredRowId, getId(row))) {
      return;
    }
    setHoveredRowId(getId(row));
  };

  const clearHoveredRow = (): void => {
    setHoveredRowId(null);
  };

  const isSelected = (row): boolean => {
    return selectedRowsInclude(row);
  };

  const onLimitChanged = (event): void => {
    onPaginationLimitChanged(event);
    onPaginate(null, 0);
  };

  const emptyRows = limit - Math.min(limit, totalRows - currentPage * limit);

  const tableMaxHeight = (): string => {
    if (innerScrollDisabled) {
      return '100%';
    }

    return `calc(100vh - ${tableTopOffset}px - ${
      actionBarRef.current?.offsetHeight
    }px - ${headerHeight}px - ${loadingIndicatorHeight}px - ${theme.spacing(
      1,
    )}px)`;
  };

  const getGridTemplateColumn = (): string => {
    const checkbox = checkable ? 'min-content ' : '';

    const columns = columnConfiguration
      .map(({ width }) => {
        if (isNil(width)) {
          return 'auto';
        }

        return typeof width === 'number' ? `${width}px` : width;
      })
      .join(' ');

    return `${checkbox}${columns}`;
  };

  return (
    <>
      {loading && tableData.length > 0 && (
        <LinearProgress className={classes.loadingIndicator} />
      )}
      {(!loading || (loading && tableData.length < 1)) && (
        <div className={classes.loadingIndicator} />
      )}
      <div
        className={classes.container}
        ref={containerRef as RefObject<HTMLDivElement>}
      >
        <div
          className={classes.actionBar}
          ref={actionBarRef as RefObject<HTMLDivElement>}
        >
          <div className={classes.actions}>{Actions}</div>
          {paginated ? (
            <StyledPagination
              className={classes.paginationElement}
              rowsPerPageOptions={[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]}
              labelDisplayedRows={labelDisplayedRows}
              labelRowsPerPage={labelRowsPerPage}
              colSpan={3}
              count={totalRows}
              rowsPerPage={limit}
              page={currentPage}
              SelectProps={{
                native: true,
              }}
              onChangePage={onPaginate}
              onChangeRowsPerPage={onLimitChanged}
              ActionsComponent={PaginationActions}
            />
          ) : null}
        </div>
        <Paper
          style={{
            maxHeight: tableMaxHeight(),
          }}
          className={classes.paper}
          elevation={1}
          square
        >
          <Table
            size="small"
            stickyHeader
            className={classes.table}
            style={{
              gridTemplateColumns: getGridTemplateColumn(),
            }}
          >
            <ListingHeader
              numSelected={selectedRows.length}
              order={sorto}
              checkable={checkable}
              orderBy={sortf}
              onSelectAllClick={selectAllRows}
              onRequestSort={handleRequestSort}
              rowCount={limit - emptyRows}
              headColumns={columnConfiguration}
            />

            <TableBody
              onMouseLeave={clearHoveredRow}
              className={classes.tableBody}
            >
              {tableData.map((row) => {
                const isRowSelected = isSelected(row);
                const isRowHovered = equals(hoveredRowId, getId(row));

                return (
                  <ListingRow
                    tabIndex={-1}
                    key={getId(row)}
                    onMouseOver={(): void => hoverRow(row)}
                    onFocus={(): void => hoverRow(row)}
                    onClick={(): void => {
                      onRowClick(row);
                    }}
                    isHovered={isRowHovered}
                    isSelected={isRowSelected}
                    row={row}
                    rowColorConditions={rowColorConditions}
                  >
                    {checkable && (
                      <Cell
                        align="left"
                        onClick={(event): void => selectRow(event, row)}
                        isRowHovered={isRowHovered}
                        row={row}
                        rowColorConditions={rowColorConditions}
                      >
                        <Checkbox
                          checked={isRowSelected}
                          inputProps={{
                            'aria-label': `Select row ${getId(row)}`,
                          }}
                          disabled={disableRowCheckCondition(row)}
                        />
                      </Cell>
                    )}

                    {columnConfiguration.map((column) => (
                      <DataCell
                        key={`${getId(row)}-${column.id}`}
                        column={column}
                        row={row}
                        listingCheckable={checkable}
                        isRowSelected={isRowSelected}
                        isRowHovered={isRowHovered}
                        rowColorConditions={rowColorConditions}
                      />
                    ))}
                  </ListingRow>
                );
              })}
              {tableData.length < 1 && (
                <TableRow tabIndex={-1} className={classes.emptyDataRow}>
                  <Cell
                    className={classes.emptyDataCell}
                    isRowHovered={false}
                    align="center"
                    style={{
                      gridColumn: `auto / span ${
                        columnConfiguration.length + 1
                      }`,
                    }}
                  >
                    {loading ? <ListingLoadingSkeleton /> : emptyDataMessage}
                  </Cell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </Paper>
      </div>
    </>
  );
};

interface MemoizedListingProps extends Props {
  memoProps?: Array<unknown>;
}

export const MemoizedListing = ({
  memoProps = [],
  limit = 10,
  columnConfiguration,
  tableData = [],
  currentPage = 0,
  totalRows = 0,
  checkable = false,
  emptyDataMessage = 'No results found',
  rowColorConditions = [],
  labelRowsPerPage = 'Rows per page',
  loading = false,
  paginated = true,
  selectedRows = [],
  sorto = undefined,
  sortf = undefined,
  innerScrollDisabled = false,
  ...props
}: MemoizedListingProps): JSX.Element =>
  useMemoComponent({
    Component: (
      <Listing
        limit={limit}
        columnConfiguration={columnConfiguration}
        tableData={tableData}
        currentPage={currentPage}
        totalRows={totalRows}
        checkable={checkable}
        emptyDataMessage={emptyDataMessage}
        rowColorConditions={rowColorConditions}
        labelRowsPerPage={labelRowsPerPage}
        loading={loading}
        paginated={paginated}
        selectedRows={selectedRows}
        sorto={sorto}
        sortf={sortf}
        innerScrollDisabled={innerScrollDisabled}
        {...props}
      />
    ),
    memoProps: [
      ...memoProps,
      limit,
      tableData,
      currentPage,
      totalRows,
      checkable,
      emptyDataMessage,
      labelRowsPerPage,
      loading,
      paginated,
      selectedRows,
      sorto,
      sortf,
      innerScrollDisabled,
    ],
  });

export default Listing;
