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

import { Box, LinearProgress, Table, TableBody } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { useKeyObserver, useMemoComponent } from '../utils';

import ListingActionBar from './ActionBar';
import Cell from './Cell';
import DataCell from './Cell/DataCell';
import Checkbox from './Checkbox';
import getCumulativeOffset from './getCumulativeOffset';
import {
  Column,
  ColumnConfiguration,
  PredefinedRowSelection,
  RowColorCondition,
  RowId,
  SortOrder
} from './models';
import ListingRow from './Row/Row';
import { labelNoResultFound } from './translatedLabels';
import useResizeObserver from './useResizeObserver';
import useStyleTable from './useStyleTable';
import { loadingIndicatorHeight, useStyles } from './Listing.styles';
import { EmptyResult } from './EmptyResult/EmptyResult';
import { SkeletonLoader } from './Row/SkeletonLoaderRows';
import { ListingHeader } from './Header';

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

interface CustomStyle {
  customStyleViewerModeContainer?: string;
  customStyleViewerModeIcon?: string;
}
interface ViewerModeConfiguration {
  customStyle?: CustomStyle;
  disabled?: boolean;
  labelViewerMode?: string;
  onClick: () => void;
  testId?: string;
  title?: string;
}

export interface Props<TRow> {
  actions?: JSX.Element;
  actionsBarMemoProps?: Array<unknown>;
  checkable?: boolean;
  columnConfiguration?: ColumnConfiguration;
  columns: Array<Column>;
  currentPage?: number;
  customPaginationClassName?: string;
  disableRowCheckCondition?: (row) => boolean;
  disableRowCondition?: (row) => boolean;
  getHighlightRowCondition?: (row: TRow) => boolean;
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
  onSort?: (sortParams: { sortField: string; sortOrder: SortOrder }) => void;
  paginated?: boolean;
  predefinedRowsSelection?: Array<PredefinedRowSelection>;
  rowColorConditions?: Array<RowColorCondition>;
  rows?: Array<TRow>;
  selectedRows?: Array<TRow>;
  sortField?: string;
  sortOrder?: SortOrder;
  totalRows?: number;
  viewMode?: ListingVariant;
  viewerModeConfiguration?: ViewerModeConfiguration;
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
  customPaginationClassName,
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
  viewMode = ListingVariant.compact,
  widthToMoveTablePagination,
  getHighlightRowCondition,
  viewerModeConfiguration
}: Props<TRow>): JSX.Element => {
  const currentVisibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });
  const { dataStyle, getGridTemplateColumn } = useStyleTable({
    checkable,
    currentVisibleColumns,
    viewMode
  });

  const { classes, theme } = useStyles({
    dataStyle,
    getGridTemplateColumn,
    limit,
    rows,
    viewMode
  });
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
    }px - ${
      dataStyle.header.height
    }px - ${loadingIndicatorHeight}px - ${theme.spacing(1)})`;
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

  const areColumnsEditable = not(isNil(onSelectColumns));

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
            customPaginationClassName={customPaginationClassName}
            limit={limit}
            moveTablePagination={moveTablePagination}
            paginated={paginated}
            totalRows={totalRows}
            viewMode={viewMode}
            viewerModeConfiguration={viewerModeConfiguration}
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
              areColumnsEditable={areColumnsEditable}
              checkable={checkable}
              columnConfiguration={columnConfiguration}
              columns={columns}
              memoProps={headerMemoProps}
              predefinedRowsSelection={predefinedRowsSelection}
              rowCount={limit - emptyRows}
              selectedRowCount={selectedRows.length}
              sortField={sortField}
              sortOrder={sortOrder}
              viewMode={viewMode}
              onSelectAllClick={selectAllRows}
              onSelectColumns={onSelectColumns}
              onSelectRowsWithCondition={onSelectRowsWithCondition}
              onSort={onSort}
            />

            <TableBody
              className={classes.tableBody}
              component="div"
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
                    viewMode={viewMode}
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
                        getHighlightRowCondition={getHighlightRowCondition}
                        isRowHovered={isRowHovered}
                        isRowSelected={isRowSelected}
                        key={`${getId(row)}-${column.id}`}
                        row={row}
                        rowColorConditions={rowColorConditions}
                        viewMode={viewMode}
                      />
                    ))}
                  </ListingRow>
                );
              })}

              {rows.length < 1 &&
                (loading ? (
                  <SkeletonLoader rows={limit} />
                ) : (
                  <EmptyResult label={t(labelNoResultFound)} />
                ))}
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
  viewMode,
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
        viewMode={viewMode}
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
      innerScrollDisabled,
      viewMode
    ]
  });

export default Listing;
export { getVisibleColumns };
