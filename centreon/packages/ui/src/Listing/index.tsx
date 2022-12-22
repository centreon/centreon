/* eslint-disable react/no-array-index-key */

import * as React from 'react';

import {
  concat,
  differenceWith,
  equals,
  filter,
  findIndex,
  gt,
  gte,
  includes,
  isNil,
  last,
  length,
  lt,
  map,
  not,
  pick,
  prop,
  propEq,
  reject,
  slice,
  subtract,
  uniqBy
} from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  Box,
  LinearProgress,
  Table,
  TableBody,
  TableRow,
  useTheme
} from '@mui/material';

import useKeyObserver from '../utils/useKeyObserver';
import useMemoComponent from '../utils/useMemoComponent';

import ListingActionBar from './ActionBar';
import Cell from './Cell';
import DataCell from './Cell/DataCell';
import Checkbox from './Checkbox';
import getCumulativeOffset from './getCumulativeOffset';
import ListingHeader, { headerHeight } from './Header/index';
import {
  Column,
  ColumnConfiguration,
  PredefinedRowSelection,
  RowColorCondition,
  RowId,
  SortOrder,
  HeaderTable
} from './models';
import ListingRow from './Row';
import ListingLoadingSkeleton from './Skeleton';
import { labelNoResultFound } from './translatedLabels';
import useResizeObserver from './useResizeObserver';
import useStyleTable from './useStyleTable';

const getVisibleColumns = ({
  columnConfiguration,
  columns
}: Pick<Props<unknown>, 'columnConfiguration' | 'columns'>): Array<Column> => {
  const selectedColumnIds = columnConfiguration?.selectedColumnIds;

  if (isNil(selectedColumnIds)) {
    return columns;
  }

  return selectedColumnIds.map((id) =>
    columns.find(propEq('id', id))
  ) as Array<Column>;
};

const loadingIndicatorHeight = 3;

interface StylesProps {
  getGridTemplateColumn: string;
  headerData: HeaderTable;
  rows: Array<unknown>;
}

const useStyles = makeStyles<StylesProps>()(
  (theme, { headerData, getGridTemplateColumn, rows }) => ({
    actionBar: {
      alignItems: 'center',
      display: 'flex'
    },
    checkbox: {
      justifyContent: 'center'
    },
    container: {
      background: 'none',
      display: 'flex',
      flexDirection: 'column',
      height: '100%',
      width: '100%'
    },
    emptyDataCell: {
      paddingLeft: theme.spacing(2)
    },
    emptyDataRow: {
      display: 'contents'
    },
    loadingIndicator: {
      height: loadingIndicatorHeight,
      width: '100%'
    },
    table: {
      alignItems: 'center',
      display: 'grid',
      gridTemplateColumns: getGridTemplateColumn,
      gridTemplateRows: `${theme.spacing(headerData.height / 8)} repeat(${
        rows?.length
      },30px)`,
      position: 'relative',
      'thead div:nth-child(n)': {
        backgroundColor: headerData.backgroundColor,
        color: headerData.color,
        height: headerData.height,
        padding: 0
      }
    },
    tableBody: {
      display: 'contents',
      position: 'relative'
    },
    tableWrapper: {
      borderBottom: 'none',
      overflow: 'auto'
    }
  })
);

export interface Props<TRow> {
  actions?: JSX.Element;
  actionsBarMemoProps?: Array<unknown>;
  checkable?: boolean;
  columnConfiguration?: ColumnConfiguration;
  columns: Array<Column>;
  currentPage?: number;
  disableRowCheckCondition?: (row) => boolean;
  disableRowCondition?: (row) => boolean;
  getId?: (row: TRow) => RowId;
  headerMemoProps?: Array<unknown>;
  innerScrollDisabled?: boolean;
  limit?: number;
  loading?: boolean;
  moveTablePagination?: boolean;
  onLimitChange?: (limit) => void;
  onPaginate?: (page) => void;
  onResetColumns?: () => void;
  onRowClick?: (row: TRow) => void;
  onSelectColumns?: (selectedColumnIds: Array<string>) => void;
  onSelectRows?: (rows: Array<TRow>) => void;
  onSort?: (sortParams) => void;
  paginated?: boolean;
  predefinedRowsSelection?: Array<PredefinedRowSelection>;
  rowColorConditions?: Array<RowColorCondition>;
  rows?: Array<TRow>;
  selectedRows?: Array<TRow>;
  sortField?: string;
  sortOrder?: SortOrder;
  totalRows?: number;
  widthToMoveTablePagination?: number;
}

const defaultColumnConfiguration = {
  sortable: false
};

export const performanceRowsLimit = 60;

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
  getId = ({ id }): RowId => id,
  headerMemoProps = [],
  predefinedRowsSelection = [],
  actionsBarMemoProps = [],
  moveTablePagination,
  widthToMoveTablePagination
}: Props<TRow>): JSX.Element => {
  const currentVisibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });
  const { headerData, getGridTemplateColumn } = useStyleTable({
    checkable,
    currentVisibleColumns
  });
  const { classes } = useStyles({ getGridTemplateColumn, headerData, rows });
  const { t } = useTranslation();

  const [tableTopOffset, setTableTopOffset] = React.useState(0);
  const [hoveredRowId, setHoveredRowId] = React.useState<RowId | null>(null);
  const [shiftKeyDownRowPivot, setShiftKeyDownRowPivot] = React.useState<
    number | null
  >(null);
  const [lastSelectionIndex, setLastSelectionIndex] = React.useState<
    number | null
  >(null);
  const containerRef = React.useRef<HTMLDivElement>();
  const actionBarRef = React.useRef<HTMLDivElement>();

  const theme = useTheme();

  useResizeObserver({
    onResize: () => {
      setTableTopOffset(getCumulativeOffset(containerRef.current));
    },
    ref: containerRef
  });

  const { isShiftKeyDown } = useKeyObserver();

  const haveSameId = (row: TRow, rowToCompare: TRow): boolean =>
    equals(getId(row), getId(rowToCompare));

  const selectedRowsInclude = (row): boolean => {
    return !!selectedRows.find((includedRow) =>
      equals(getId(includedRow), getId(row))
    );
  };

  const selectAllRows = (event): void => {
    if (
      event.target.checked &&
      event.target.getAttribute('data-indeterminate') === 'false'
    ) {
      onSelectRows(reject(disableRowCheckCondition, rows));
      setLastSelectionIndex(null);

      return;
    }

    onSelectRows([]);
    setLastSelectionIndex(null);
  };

  const onSelectRowsWithCondition = (condition: (row) => boolean): void => {
    onSelectRows(reject(disableRowCheckCondition, filter(condition, rows)));
    setLastSelectionIndex(null);
  };

  interface GetSelectedRowsWithShiftKeyProps {
    compareFunction;
    comparisonSliceEndIndex: number;
    comparisonSliceStartIndex: number;
    newSelection: Array<TRow>;
    selectedRowIndex: number;
    selectedRowsIndex: Array<number>;
  }

  const getSelectedRowsWithShiftKey = ({
    newSelection,
    selectedRowsIndex,
    selectedRowIndex,
    compareFunction,
    comparisonSliceStartIndex,
    comparisonSliceEndIndex
  }: GetSelectedRowsWithShiftKeyProps): Array<TRow> => {
    if (includes(selectedRowIndex, selectedRowsIndex)) {
      return differenceWith(haveSameId, selectedRows, newSelection);
    }
    if (
      compareFunction(lastSelectionIndex, last(selectedRowsIndex) as number)
    ) {
      const normalizedNewSelection = slice(
        comparisonSliceStartIndex,
        comparisonSliceEndIndex,
        newSelection
      );

      const newSelectionWithCurrentSelection = concat(
        selectedRows,
        normalizedNewSelection
      );

      const newSelectedRowsWithUniqElements = uniqBy(
        getId,
        newSelectionWithCurrentSelection
      );

      return newSelectedRowsWithUniqElements;
    }
    const newSelectedRowsWithCurrentSelection = concat(
      selectedRows,
      newSelection
    );

    const newSelectedRowsWithUniqElements = uniqBy(
      getId,
      newSelectedRowsWithCurrentSelection
    );

    return newSelectedRowsWithUniqElements;
  };

  const selectRowsWithShiftKey = (selectedRowIndex: number): void => {
    const lastSelectedIndex = lastSelectionIndex as number;
    if (isNil(shiftKeyDownRowPivot)) {
      const selectedRowsFromTheStart = slice(0, selectedRowIndex + 1, rows);

      onSelectRows(reject(disableRowCheckCondition, selectedRowsFromTheStart));

      return;
    }

    const selectedRowsIndex = map(
      (row) =>
        findIndex((listingRow) => equals(getId(row), getId(listingRow)), rows),
      selectedRows
    ).sort(subtract);

    if (selectedRowIndex < lastSelectedIndex) {
      const newSelection = slice(
        selectedRowIndex,
        (lastSelectionIndex as number) + 1,
        rows
      );
      onSelectRows(
        reject(
          disableRowCheckCondition,
          getSelectedRowsWithShiftKey({
            compareFunction: gt,
            comparisonSliceEndIndex: -1,
            comparisonSliceStartIndex: 0,
            newSelection,
            selectedRowIndex,
            selectedRowsIndex
          })
        )
      );

      return;
    }

    const newSelection = slice(lastSelectedIndex, selectedRowIndex + 1, rows);
    onSelectRows(
      reject(
        disableRowCheckCondition,
        getSelectedRowsWithShiftKey({
          compareFunction: lt,
          comparisonSliceEndIndex: length(newSelection),
          comparisonSliceStartIndex: 1,
          newSelection,
          selectedRowIndex,
          selectedRowsIndex
        })
      )
    );
  };

  const selectRow = (event: React.MouseEvent, row): void => {
    event.preventDefault();
    event.stopPropagation();
    // This prevents unwanted text selection
    document.getSelection()?.removeAllRanges();

    const selectedRowIndex = findIndex(
      (listingRow) => equals(getId(row), getId(listingRow)),
      rows
    );

    if (isShiftKeyDown) {
      selectRowsWithShiftKey(selectedRowIndex);
      setLastSelectionIndex(selectedRowIndex);

      return;
    }

    setLastSelectionIndex(selectedRowIndex);

    if (disableRowCheckCondition(row)) {
      return;
    }

    if (selectedRowsInclude(row)) {
      onSelectRows(
        selectedRows.filter((entity) => !equals(getId(entity), getId(row)))
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
      1
    )})`;
  };

  const changeLimit = (updatedLimit: string): void => {
    onLimitChange?.(Number(updatedLimit));
  };

  const visibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });

  React.useEffect(() => {
    if (not(isShiftKeyDown)) {
      setShiftKeyDownRowPivot(null);

      return;
    }
    setShiftKeyDownRowPivot(lastSelectionIndex);
  }, [isShiftKeyDown, lastSelectionIndex]);

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
        ref={containerRef as React.RefObject<HTMLDivElement>}
      >
        <div
          className={classes.actionBar}
          ref={actionBarRef as React.RefObject<HTMLDivElement>}
        >
          <ListingActionBar
            actions={actions}
            actionsBarMemoProps={actionsBarMemoProps}
            columnConfiguration={columnConfiguration}
            columns={columns}
            currentPage={currentPage}
            limit={limit}
            moveTablePagination={moveTablePagination}
            paginated={paginated}
            totalRows={totalRows}
            widthToMoveTablePagination={widthToMoveTablePagination}
            onLimitChange={changeLimit}
            onPaginate={onPaginate}
            onResetColumns={onResetColumns}
            onSelectColumns={onSelectColumns}
          />
        </div>
        <Box
          className={classes.tableWrapper}
          component="div"
          style={{
            maxHeight: tableMaxHeight()
          }}
        >
          <Table
            stickyHeader
            className={classes.table}
            component="div"
            role={undefined}
            size="small"
          >
            <ListingHeader
              checkable={checkable}
              columnConfiguration={columnConfiguration}
              columns={columns}
              memoProps={headerMemoProps}
              predefinedRowsSelection={predefinedRowsSelection}
              rowCount={limit - emptyRows}
              selectedRowCount={selectedRows.length}
              sortField={sortField}
              sortOrder={sortOrder}
              onSelectAllClick={selectAllRows}
              onSelectColumns={onSelectColumns}
              onSelectRowsWithCondition={onSelectRowsWithCondition}
              onSort={onSort}
            />

            <TableBody
              className={classes.tableBody}
              onMouseLeave={clearHoveredRow}
            >
              {rows.map((row, index) => {
                const isRowSelected = isSelected(row);

                const isRowHovered = equals(hoveredRowId, getId(row));

                return (
                  <ListingRow
                    checkable={checkable}
                    columnConfiguration={columnConfiguration}
                    columnIds={columns.map(prop('id'))}
                    disableRowCondition={disableRowCondition}
                    isHovered={isRowHovered}
                    isSelected={isRowSelected}
                    isShiftKeyDown={isShiftKeyDown}
                    key={
                      gte(limit, performanceRowsLimit)
                        ? `row_${index}`
                        : getId(row)
                    }
                    lastSelectionIndex={lastSelectionIndex}
                    limit={limit}
                    row={row}
                    rowColorConditions={rowColorConditions}
                    shiftKeyDownRowPivot={shiftKeyDownRowPivot}
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
                        // compact
                        align="left"
                        className={classes.checkbox}
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
                            'aria-label': `Select row ${getId(row)}`
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
                    disableRowCondition={(): boolean => false}
                    isRowHovered={false}
                    style={{
                      gridColumn: `auto / span ${
                        checkable ? columns.length + 1 : columns.length
                      }`
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
        </Box>
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
  columnConfiguration,
  moveTablePagination,
  widthToMoveTablePagination,
  ...props
}: MemoizedListingProps<TRow>): JSX.Element =>
  useMemoComponent({
    Component: (
      <Listing
        checkable={checkable}
        columnConfiguration={columnConfiguration}
        columns={columns}
        currentPage={currentPage}
        innerScrollDisabled={innerScrollDisabled}
        limit={limit}
        loading={loading}
        moveTablePagination={moveTablePagination}
        paginated={paginated}
        rowColorConditions={rowColorConditions}
        rows={rows}
        selectedRows={selectedRows}
        sortField={sortField}
        sortOrder={sortOrder}
        totalRows={totalRows}
        widthToMoveTablePagination={widthToMoveTablePagination}
        {...props}
      />
    ),
    memoProps: [
      ...memoProps,
      pick(
        ['id', 'label', 'disabled', 'width', 'shortLabel', 'sortField'],
        columns
      ),
      columnConfiguration,
      limit,
      widthToMoveTablePagination,
      rows,
      currentPage,
      totalRows,
      checkable,
      loading,
      paginated,
      selectedRows,
      sortOrder,
      sortField,
      innerScrollDisabled
    ]
  });

export default Listing;
export { getVisibleColumns };
