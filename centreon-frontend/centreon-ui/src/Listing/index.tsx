import React, { useState, useRef, useEffect, RefObject } from 'react';

import ResizeObserver from 'resize-observer-polyfill';
import clsx from 'clsx';

import { withStyles, makeStyles, Theme } from '@material-ui/core/styles';
import {
  Table,
  TableBody,
  Paper,
  LinearProgress,
  TableCell,
  TableRow,
  Checkbox,
  Typography,
  Tooltip,
} from '@material-ui/core';

import ListingHeader, { useCellStyles } from './Header';
import ListingRow from './Row';
import { ColumnType } from './models';
import PaginationActions from './PaginationActions';
import StyledPagination from './Pagination';
import ListingLoadingSkeleton from './Skeleton';

const loadingIndicatorHeight = 3;

const haveSameIds = (a, b): boolean => a.id === b.id;

const BodyTableCell = withStyles((theme) => ({
  root: {
    paddingTop: theme.spacing(0.5),
    paddingBottom: theme.spacing(0.5),
    paddingRight: theme.spacing(0.5),
  },
}))(TableCell);

const useStyles = makeStyles<Theme>((theme) => ({
  paper: {
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
  pagination: {
    marginLeft: 'auto',
    display: 'flex',
    flexDirection: 'row-reverse',
    padding: 0,
  },
  loadingIndicator: {
    width: '100%',
    height: loadingIndicatorHeight,
  },
  truncated: {
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    maxWidth: 150,
    whiteSpace: 'nowrap',
  },
}));

const cumulativeOffset = (element): number => {
  if (!element || !element.offsetParent) {
    return 0;
  }

  return cumulativeOffset(element.offsetParent) + element.offsetTop;
};

export interface Props {
  checkable?: boolean;
  disableRowCheckCondition?;
  currentPage?;
  columnConfiguration;
  emptyDataMessage?: string;
  rowColorConditions?;
  labelDisplayedRows?: (fromToCount) => string;
  labelRowsPerPage?: string;
  limit?: number;
  loading?: boolean;
  loadingDataMessage?: string;
  onPaginate?: (event, value) => void;
  onPaginationLimitChanged?: (event) => void;
  onRowClick?: (row) => void;
  onSelectRows?: (rows) => void;
  onSort?: (sortParams) => void;
  paginated?: boolean;
  selectedRows?;
  sorto?: 'asc' | 'desc';
  sortf?: string;
  tableData?;
  totalRows?;
  Actions?: JSX.Element;
  innerScrollDisabled?: boolean;
  expanded?: boolean;
}

const Listing = ({
  limit = 10,
  columnConfiguration,
  tableData = [],
  currentPage = 0,
  totalRows = 0,
  checkable = false,
  disableRowCheckCondition = (): boolean => false,
  emptyDataMessage = 'No results found',
  rowColorConditions = [],
  labelDisplayedRows = ({ from, to, count }): string =>
    `${from}-${to} of ${count}`,
  labelRowsPerPage = 'Rows per page',
  loading = false,
  onPaginate = (): void => undefined,
  onPaginationLimitChanged = (): void => undefined,
  onRowClick = (): void => undefined,
  onSelectRows = (): void => undefined,
  onSort = (): void => undefined,
  paginated = true,
  selectedRows = [],
  sorto = undefined,
  sortf = undefined,
  Actions,
  innerScrollDisabled = false,
}: Props): JSX.Element => {
  const [tableTopOffset, setTableTopOffset] = useState(0);
  const [hoveredRowId, setHoveredRowId] = useState(null);

  const tableBody = useRef<HTMLTableSectionElement>();

  const classes = useStyles();
  const cellClasses = useCellStyles(checkable);

  useEffect(() => {
    const ro = new ResizeObserver(() => {
      setTableTopOffset(cumulativeOffset(tableBody.current));
    });

    ro.observe(tableBody.current as Element);
  }, []);

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

  const getColumnCell = ({ row, column }): JSX.Element | null => {
    const cellKey = `${row.id}-${column.id}`;
    const isRowHovered = hoveredRowId === row.id;
    const isRowSelected = isSelected(row);

    const cellByColumnType = {
      [ColumnType.string]: (): JSX.Element => {
        const {
          getFormattedString,
          width,
          getTruncateCondition,
          getColSpan,
        } = column;

        const isTruncated = getTruncateCondition?.(isRowSelected);
        const colSpan = getColSpan?.(isRowSelected);

        const formattedString = getFormattedString(row) || '';

        return (
          <BodyTableCell
            key={cellKey}
            align="left"
            style={{ width: width || 'auto' }}
            className={cellClasses.cell}
            colSpan={colSpan}
          >
            {isTruncated && (
              <Tooltip title={formattedString}>
                <Typography
                  variant="body2"
                  className={clsx({ [classes.truncated]: isTruncated })}
                >
                  {formattedString}
                </Typography>
              </Tooltip>
            )}
            {!isTruncated && formattedString}
          </BodyTableCell>
        );
      },
      [ColumnType.component]: (): JSX.Element | null => {
        const { Component, getHiddenCondition, width, clickable } = column;

        const isCellHidden = getHiddenCondition?.(isRowSelected);

        if (isCellHidden) {
          return null;
        }

        return (
          <BodyTableCell
            align="left"
            key={cellKey}
            style={{ width: width || 'auto' }}
            onClick={(e): void => {
              if (!clickable) {
                return;
              }
              e.preventDefault();
              e.stopPropagation();
            }}
            className={cellClasses.cell}
          >
            <Component
              row={row}
              isSelected={isRowSelected}
              isHovered={isRowHovered}
            />
          </BodyTableCell>
        );
      },
    };

    return cellByColumnType[column.type]();
  };

  const emptyRows = limit - Math.min(limit, totalRows - currentPage * limit);

  const tableMaxHeight = (): string => {
    return innerScrollDisabled
      ? '100%'
      : `calc(100vh - ${tableTopOffset}px - 25px)`;
  };

  return (
    <>
      {loading && tableData.length > 0 && (
        <LinearProgress className={classes.loadingIndicator} />
      )}
      {(!loading || (loading && tableData.length < 1)) && (
        <div className={classes.loadingIndicator} />
      )}
      <div className={classes.paper}>
        <div className={classes.actionBar}>
          <div className={classes.actions}>{Actions}</div>
          {paginated ? (
            <StyledPagination
              className={classes.pagination}
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
            />

            <TableBody
              ref={tableBody as RefObject<HTMLTableSectionElement>}
              onMouseLeave={clearHoveredRow}
              style={{
                position: 'relative',
              }}
            >
              {tableData.map((row) => {
                const isRowSelected = isSelected(row);

                return (
                  <ListingRow
                    tabIndex={-1}
                    key={row.id}
                    onMouseOver={(): void => hoverRow(row.id)}
                    onFocus={(): void => hoverRow(row.id)}
                    onClick={(): void => {
                      onRowClick(row);
                    }}
                    isHovered={hoveredRowId === row.id}
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

                    {columnConfiguration.map((column) =>
                      getColumnCell({
                        column,
                        row,
                      }),
                    )}
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

export default Listing;
