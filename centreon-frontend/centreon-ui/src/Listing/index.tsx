import React, { useState, useRef, RefObject } from 'react';

import { makeStyles, Theme } from '@material-ui/core/styles';
import {
  Table,
  TableBody,
  Paper,
  LinearProgress,
  TableRow,
  Checkbox,
} from '@material-ui/core';

import useMemoComponent from '../utils/useMemoComponent';

import ListingHeader from './Header';
import ListingRow from './Row';
import PaginationActions from './PaginationActions';
import StyledPagination from './Pagination';
import ListingLoadingSkeleton from './Skeleton';
import useResizeObserver from './useResizeObserver';
import getCumulativeOffset from './getCumulativeOffset';
import ColumnCell, { BodyTableCell } from './ColumnCell';

const loadingIndicatorHeight = 3;

const haveSameIds = (a, b): boolean => a.id === b.id;

const useStyles = makeStyles<Theme>((theme) => ({
  paperElement: {
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
}));

export interface Props {
  checkable?: boolean;
  currentPage?;
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
}: Props): JSX.Element => {
  const [tableTopOffset, setTableTopOffset] = useState(0);
  const [hoveredRowId, setHoveredRowId] = useState<string | number | null>(
    null,
  );

  const paperRef = useRef<HTMLDivElement>();
  const paginationElement = useRef<HTMLDivElement>();
  const tableHeaderElement = useRef<HTMLTableSectionElement>();

  const classes = useStyles();

  useResizeObserver({
    ref: paperRef,
    onResize: () => {
      setTableTopOffset(getCumulativeOffset(paperRef.current));
    },
  });

  const selectedRowsInclude = (row): boolean => {
    return !!selectedRows.find((includedRow) => haveSameIds(includedRow, row));
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
      onSelectRows(selectedRows.filter((entity) => !haveSameIds(entity, row)));
      return;
    }
    onSelectRows([...selectedRows, row]);
  };

  const hoverRow = (id): void => {
    if (hoveredRowId === id) {
      return;
    }
    setHoveredRowId(id);
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

    return `calc(100vh - ${tableTopOffset}px - ${paginationElement.current?.clientHeight}px - ${tableHeaderElement.current?.clientHeight}px)`;
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
        className={classes.paperElement}
        ref={paperRef as RefObject<HTMLDivElement>}
      >
        <div
          className={classes.actionBar}
          ref={paginationElement as RefObject<HTMLDivElement>}
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
            overflow: 'auto',
            maxHeight: tableMaxHeight(),
          }}
          elevation={1}
          square
        >
          <Table size="small" stickyHeader>
            <ListingHeader
              numSelected={selectedRows.length}
              order={sorto}
              checkable={checkable}
              orderBy={sortf}
              onSelectAllClick={selectAllRows}
              onRequestSort={handleRequestSort}
              rowCount={limit - emptyRows}
              headColumns={columnConfiguration}
              ref={tableHeaderElement as RefObject<HTMLTableSectionElement>}
            />

            <TableBody
              onMouseLeave={clearHoveredRow}
              style={{
                position: 'relative',
              }}
            >
              {tableData.map((row) => {
                const isRowSelected = isSelected(row);
                const isRowHovered = hoveredRowId === row.id;

                return (
                  <ListingRow
                    tabIndex={-1}
                    key={row.id}
                    onMouseOver={(): void => hoverRow(row.id)}
                    onFocus={(): void => hoverRow(row.id)}
                    onClick={(): void => {
                      onRowClick(row);
                    }}
                    isHovered={isRowHovered}
                    isSelected={isRowSelected}
                    row={row}
                    rowColorConditions={rowColorConditions}
                  >
                    {checkable ? (
                      <BodyTableCell
                        align="left"
                        onClick={(event): void => selectRow(event, row)}
                        padding="checkbox"
                      >
                        <Checkbox
                          size="small"
                          color="primary"
                          checked={isRowSelected}
                          inputProps={{
                            'aria-label': `Select row ${row.id}`,
                          }}
                          disabled={disableRowCheckCondition(row)}
                        />
                      </BodyTableCell>
                    ) : null}

                    {columnConfiguration.map((column) => (
                      <ColumnCell
                        key={`${row.id}-${column.id}`}
                        column={column}
                        row={row}
                        listingCheckable={checkable}
                        isRowSelected={isRowSelected}
                        isRowHovered={isRowHovered}
                      />
                    ))}
                  </ListingRow>
                );
              })}
              {tableData.length < 1 && (
                <TableRow tabIndex={-1}>
                  <BodyTableCell
                    colSpan={columnConfiguration.length + 1}
                    align="center"
                  >
                    {loading ? <ListingLoadingSkeleton /> : emptyDataMessage}
                  </BodyTableCell>
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
