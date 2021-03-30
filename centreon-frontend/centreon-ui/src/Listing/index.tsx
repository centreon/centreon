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

export interface Props<TRow> {
  checkable?: boolean;
  currentPage?: number;
  columns: Array<Column>;
  columnConfiguration?: ColumnConfiguration;
  onSelectColumns?: (selectedColumnIds: Array<string>) => void;
  onResetColumns?: () => void;
  rowColorConditions?: Array<RowColorCondition>;
  limit?: number;
  loading?: boolean;
  loadingDataMessage?: string;
  paginated?: boolean;
  selectedRows?: Array<TRow>;
  sortOrder?: SortOrder;
  sortField?: string;
  rows?: Array<TRow>;
  totalRows?: number;
  innerScrollDisabled?: boolean;
  expanded?: boolean;
  actions?: JSX.Element;
  disableRowCheckCondition?;
  onPaginate?: (page) => void;
  onLimitChange?: (limit) => void;
  onRowClick?: (row: TRow) => void;
  onSelectRows?: (rows: Array<TRow>) => void;
  onSort?: (sortParams) => void;
  getId?: (row: TRow) => RowId;
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
      columns,
      columnConfiguration,
    })
      .map(({ width }) => {
        if (isNil(width)) {
          return 'auto';
        }

        return typeof width === 'number' ? `${width}px` : width;
      })
      .join(' ');

    return `${checkbox}${columnTemplate}`;
  };

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
            limit={limit}
            actions={actions}
            onLimitChange={onLimitChange}
            onSelectColumns={onSelectColumns}
            onResetColumns={onResetColumns}
            onPaginate={onPaginate}
            paginated={paginated}
            currentPage={currentPage}
            totalRows={totalRows}
            columns={columns}
            columnConfiguration={columnConfiguration}
          />
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
            component="div"
            style={{
              gridTemplateColumns: getGridTemplateColumn(),
            }}
          >
            <ListingHeader
              selectedRowCount={selectedRows.length}
              sortOrder={sortOrder}
              sortField={sortField}
              checkable={checkable}
              onSelectAllClick={selectAllRows}
              onSort={onSort}
              rowCount={limit - emptyRows}
              columns={columns}
              columnConfiguration={columnConfiguration}
              onSelectColumns={onSelectColumns}
            />

            <TableBody
              onMouseLeave={clearHoveredRow}
              className={classes.tableBody}
              component="div"
            >
              {rows.map((row) => {
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
                    columnIds={columns.map(prop('id'))}
                    columnConfiguration={columnConfiguration}
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

                    {getVisibleColumns({
                      columns,
                      columnConfiguration,
                    }).map((column) => (
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
              {rows.length < 1 && (
                <TableRow
                  tabIndex={-1}
                  className={classes.emptyDataRow}
                  component="div"
                >
                  <Cell
                    className={classes.emptyDataCell}
                    isRowHovered={false}
                    align="center"
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
        limit={limit}
        columns={columns}
        rows={rows}
        currentPage={currentPage}
        totalRows={totalRows}
        checkable={checkable}
        rowColorConditions={rowColorConditions}
        loading={loading}
        paginated={paginated}
        selectedRows={selectedRows}
        sortOrder={sortOrder}
        sortField={sortField}
        innerScrollDisabled={innerScrollDisabled}
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
