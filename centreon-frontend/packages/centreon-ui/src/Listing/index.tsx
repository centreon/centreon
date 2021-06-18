import React, { useState, useRef, RefObject } from 'react';

import { equals, isNil, prop, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

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

import ListingHeader, { headerHeight } from './Header/index';
import ListingRow from './Row';
import ListingLoadingSkeleton from './Skeleton';
import useResizeObserver from './useResizeObserver';
import getCumulativeOffset from './getCumulativeOffset';
import DataCell from './Cell/DataCell';
import Cell from './Cell';
import Checkbox from './Checkbox';
import { labelNoResultFound } from './translatedLabels';
import {
  Column,
  ColumnConfiguration,
  RowColorCondition,
  RowId,
  SortOrder,
} from './models';
import ListingActionBar from './ActionBar';

const getVisibleColumns = ({
  columnConfiguration,
  columns,
}: Pick<Props<unknown>, 'columnConfiguration' | 'columns'>): Array<Column> => {
  const selectedColumnIds = columnConfiguration?.selectedColumnIds;

  if (isNil(selectedColumnIds)) {
    return columns;
  }

  return selectedColumnIds.map((id) =>
    columns.find(propEq('id', id)),
  ) as Array<Column>;
};

const loadingIndicatorHeight = 3;

const useStyles = makeStyles<Theme>((theme) => ({
  actionBar: {
    alignItems: 'center',
    display: 'flex',
  },
  container: {
    background: 'none',
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    width: '100%',
  },
  emptyDataCell: {
    paddingLeft: theme.spacing(2),
  },
  emptyDataRow: {
    display: 'contents',
  },
  loadingIndicator: {
    height: loadingIndicatorHeight,
    width: '100%',
  },
  paper: {
    overflow: 'auto',
  },
  table: {
    alignItems: 'center',
    display: 'grid',
    position: 'relative',
  },
  tableBody: {
    display: 'contents',
    position: 'relative',
  },
}));

export interface Props<TRow> {
  actions?: JSX.Element;
  checkable?: boolean;
  columnConfiguration?: ColumnConfiguration;
  columns: Array<Column>;
  currentPage?: number;
  disableRowCheckCondition?: (row) => boolean;
  disableRowCondition?: (row) => boolean;
  expanded?: boolean;
  getId?: (row: TRow) => RowId;
  innerScrollDisabled?: boolean;
  limit?: number;
  loading?: boolean;
  loadingDataMessage?: string;
  onLimitChange?: (limit) => void;
  onPaginate?: (page) => void;
  onResetColumns?: () => void;
  onRowClick?: (row: TRow) => void;
  onSelectColumns?: (selectedColumnIds: Array<string>) => void;
  onSelectRows?: (rows: Array<TRow>) => void;
  onSort?: (sortParams) => void;
  paginated?: boolean;
  rowColorConditions?: Array<RowColorCondition>;
  rows?: Array<TRow>;
  selectedRows?: Array<TRow>;
  sortField?: string;
  sortOrder?: SortOrder;
  totalRows?: number;
}

const defaultColumnConfiguration = {
  sortable: false,
};

const Listing = <TRow extends { id: RowId }>({
  limit = 10,
  columns,
  columnConfiguration = defaultColumnConfiguration,
  onResetColumns,
  onSelectColumns,
  rows = [],
  currentPage = 0,
  totalRows = 0,
  checkable = false,
  rowColorConditions = [],
  loading = false,
  paginated = true,
  selectedRows = [],
  sortOrder = undefined,
  sortField = undefined,
  innerScrollDisabled = false,
  actions,
  disableRowCheckCondition = (): boolean => false,
  disableRowCondition = (): boolean => false,
  onPaginate,
  onLimitChange,
  onRowClick = (): void => undefined,
  onSelectRows = (): void => undefined,
  onSort,
  getId = ({ id }) => id,
}: Props<TRow>): JSX.Element => {
  const { t } = useTranslation();
  const [tableTopOffset, setTableTopOffset] = useState(0);
  const [hoveredRowId, setHoveredRowId] = useState<RowId | null>(null);

  const containerRef = useRef<HTMLDivElement>();
  const actionBarRef = useRef<HTMLDivElement>();

  const classes = useStyles();

  const theme = useTheme();

  useResizeObserver({
    onResize: () => {
      setTableTopOffset(getCumulativeOffset(containerRef.current));
    },
    ref: containerRef,
  });

  const selectedRowsInclude = (row): boolean => {
    return !!selectedRows.find((includedRow) =>
      equals(getId(includedRow), getId(row)),
    );
  };

  const selectAllRows = (event): void => {
    if (
      event.target.checked &&
      event.target.getAttribute('data-indeterminate') === 'false'
    ) {
      onSelectRows(rows);
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

    const columnTemplate = getVisibleColumns({
      columnConfiguration,
      columns,
    })
      .map(({ width, shortName }) => {
        if (!isNil(shortName)) {
          return 'min-content';
        }
        if (isNil(width)) {
          return 'auto';
        }

        return typeof width === 'number' ? `${width}px` : width;
      })
      .join(' ');

    return `${checkbox}${columnTemplate}`;
  };

  const visibleColumns = getVisibleColumns({
    columnConfiguration,
    columns,
  });

  return (
    <>
      {loading && rows.length > 0 && (
        <LinearProgress className={classes.loadingIndicator} />
      )}
      {(!loading || (loading && rows.length < 1)) && (
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
          <ListingActionBar
            actions={actions}
            columnConfiguration={columnConfiguration}
            columns={columns}
            currentPage={currentPage}
            limit={limit}
            paginated={paginated}
            totalRows={totalRows}
            onLimitChange={onLimitChange}
            onPaginate={onPaginate}
            onResetColumns={onResetColumns}
            onSelectColumns={onSelectColumns}
          />
        </div>
        <Paper
          square
          className={classes.paper}
          elevation={1}
          style={{
            maxHeight: tableMaxHeight(),
          }}
        >
          <Table
            stickyHeader
            className={classes.table}
            component="div"
            size="small"
            style={{
              gridTemplateColumns: getGridTemplateColumn(),
            }}
          >
            <ListingHeader
              checkable={checkable}
              columnConfiguration={columnConfiguration}
              columns={columns}
              rowCount={limit - emptyRows}
              selectedRowCount={selectedRows.length}
              sortField={sortField}
              sortOrder={sortOrder}
              onSelectAllClick={selectAllRows}
              onSelectColumns={onSelectColumns}
              onSort={onSort}
            />

            <TableBody
              className={classes.tableBody}
              component="div"
              onMouseLeave={clearHoveredRow}
            >
              {rows.map((row) => {
                const isRowSelected = isSelected(row);

                const isRowHovered = equals(hoveredRowId, getId(row));

                return (
                  <ListingRow
                    columnConfiguration={columnConfiguration}
                    columnIds={columns.map(prop('id'))}
                    disableRowCondition={disableRowCondition}
                    isHovered={isRowHovered}
                    isSelected={isRowSelected}
                    key={getId(row)}
                    row={row}
                    rowColorConditions={rowColorConditions}
                    tabIndex={-1}
                    visibleColumns={visibleColumns}
                    onClick={(): void => {
                      onRowClick(row);
                    }}
                    onFocus={(): void => hoverRow(row)}
                    onMouseOver={(): void => hoverRow(row)}
                  >
                    {checkable && (
                      <Cell
                        align="left"
                        disableRowCondition={disableRowCondition}
                        isRowHovered={isRowHovered}
                        row={row}
                        rowColorConditions={rowColorConditions}
                        onClick={(event): void => selectRow(event, row)}
                      >
                        <Checkbox
                          checked={isRowSelected}
                          disabled={
                            disableRowCheckCondition(row) ||
                            disableRowCondition(row)
                          }
                          inputProps={{
                            'aria-label': `Select row ${getId(row)}`,
                          }}
                        />
                      </Cell>
                    )}

                    {visibleColumns.map((column) => (
                      <DataCell
                        column={column}
                        disableRowCondition={disableRowCondition}
                        isRowHovered={isRowHovered}
                        isRowSelected={isRowSelected}
                        key={`${getId(row)}-${column.id}`}
                        listingCheckable={checkable}
                        row={row}
                        rowColorConditions={rowColorConditions}
                      />
                    ))}
                  </ListingRow>
                );
              })}
              {rows.length < 1 && (
                <TableRow
                  className={classes.emptyDataRow}
                  component="div"
                  tabIndex={-1}
                >
                  <Cell
                    align="center"
                    className={classes.emptyDataCell}
                    disableRowCondition={() => false}
                    isRowHovered={false}
                    style={{
                      gridColumn: `auto / span ${columns.length + 1}`,
                    }}
                  >
                    {loading ? (
                      <ListingLoadingSkeleton />
                    ) : (
                      t(labelNoResultFound)
                    )}
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

interface MemoizedListingProps<TRow> extends Props<TRow> {
  memoProps?: Array<unknown>;
}

export const MemoizedListing = <TRow extends { id: string | number }>({
  memoProps = [],
  limit = 10,
  columns,
  rows = [],
  currentPage = 0,
  totalRows = 0,
  checkable = false,
  rowColorConditions = [],
  loading = false,
  paginated = true,
  selectedRows = [],
  sortOrder = undefined,
  sortField = undefined,
  innerScrollDisabled = false,
  ...props
}: MemoizedListingProps<TRow>): JSX.Element =>
  useMemoComponent({
    Component: (
      <Listing
        checkable={checkable}
        columns={columns}
        currentPage={currentPage}
        innerScrollDisabled={innerScrollDisabled}
        limit={limit}
        loading={loading}
        paginated={paginated}
        rowColorConditions={rowColorConditions}
        rows={rows}
        selectedRows={selectedRows}
        sortField={sortField}
        sortOrder={sortOrder}
        totalRows={totalRows}
        {...props}
      />
    ),
    memoProps: [
      ...memoProps,
      columns,
      limit,
      rows,
      currentPage,
      totalRows,
      checkable,
      loading,
      paginated,
      selectedRows,
      sortOrder,
      sortField,
      innerScrollDisabled,
    ],
  });

export default Listing;
export { getVisibleColumns };
