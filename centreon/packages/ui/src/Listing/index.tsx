import { Box, Divider, LinearProgress, Table, TableBody } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import {
  concat,
  differenceWith,
  equals,
  filter,
  findIndex,
  gt,
  identity,
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
import {
  type RefObject,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState
} from 'react';
import { useTranslation } from 'react-i18next';

import { ParentSize } from '..';
import { useKeyObserver, useMemoComponent } from '../utils';
import ListingActionBar from './ActionBar';
import Cell from './Cell';
import DataCell from './Cell/DataCell';
import Checkbox from './Checkbox';
import { EmptyResult } from './EmptyResult/EmptyResult';
import { ListingHeader } from './Header';
import type {
  Column,
  ColumnConfiguration,
  PredefinedRowSelection,
  RowColorCondition,
  RowId,
  SortOrder
} from './models';
import ListingRow from './Row/Row';
import { SkeletonLoader } from './Row/SkeletonLoaderRows';
import { subItemsPivotsAtom } from './tableAtoms';
import { labelNoResultFound as defaultLabelNoResultFound } from './translatedLabels';
import useStyleTable, { useColumnStyle } from './useStyleTable';

const subItemPrefixKey = 'listing';

const getVisibleColumns = ({
  columnConfiguration,
  columns
}: Pick<Props<unknown>, 'columnConfiguration' | 'columns'>): Array<Column> => {
  const selectedColumnIds = columnConfiguration?.selectedColumnIds;

  if (isNil(selectedColumnIds)) {
    return columns;
  }

  return selectedColumnIds
    .map((id) => columns.find(propEq(id, 'id')))
    .filter(identity) as Array<Column>;
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
  customListingComponent?: JSX.Element;
  customPaginationClassName?: string;
  disableRowCheckCondition?: (row: TRow) => boolean;
  disableRowCondition?: (row: TRow) => boolean;
  displayCustomListing?: boolean;
  getHighlightRowCondition?: (row: TRow) => boolean;
  getId?: (row: TRow) => RowId;
  headerMemoProps?: Array<unknown>;
  innerScrollDisabled?: boolean;
  isResponsive?: boolean;
  limit?: number;
  listingVariant?: ListingVariant;
  loading?: boolean;
  moveTablePagination?: boolean;
  onLimitChange?: (limit: string | number) => void;
  onPaginate?: (page: number) => void;
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
  subItems?: {
    canCheckSubItems: boolean;
    enable: boolean;
    getRowProperty: (row?: TRow) => string;
    labelCollapse: string;
    labelExpand: string;
  };
  totalRows?: number;
  approximateTotalRows?: boolean;
  onApproximateCountClick?: () => void;
  isApproximateCountLoading?: boolean;
  viewerModeConfiguration?: ViewerModeConfiguration;
  widthToMoveTablePagination?: number;
  isActionBarVisible?: boolean;
  labelNoResultFound?: string | JSX.Element;
}

const defaultColumnConfiguration = {
  sortable: false
};

const Listing = <
  TRow extends {
    id: RowId;
    internalListingParentId?: RowId;
    internalListingParentRow?: TRow;
  }
>({
  customListingComponent,
  displayCustomListing,
  limit = 10,
  columns,
  columnConfiguration = defaultColumnConfiguration,
  customPaginationClassName,
  isResponsive = false,
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
  listingVariant = ListingVariant.compact,
  widthToMoveTablePagination,
  getHighlightRowCondition,
  viewerModeConfiguration,
  subItems = {
    canCheckSubItems: false,
    enable: false,
    getRowProperty: () => '',
    labelCollapse: 'Collapse',
    labelExpand: 'Expand'
  },
  isActionBarVisible = true,
  labelNoResultFound = defaultLabelNoResultFound,
  approximateTotalRows = false,
  onApproximateCountClick,
  isApproximateCountLoading = false
}: Props<TRow>): JSX.Element => {
  const currentVisibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });
  const { dataStyle } = useStyleTable({
    listingVariant
  });
  const gridTemplateColumn = useColumnStyle({
    checkable,
    currentVisibleColumns
  });

  const { t } = useTranslation();

  const [hoveredRowId, setHoveredRowId] = useState<RowId | null>(null);
  const [shiftKeyDownRowPivot, setShiftKeyDownRowPivot] = useState<
    number | null
  >(null);
  const [lastSelectionIndex, setLastSelectionIndex] = useState<number | null>(
    null
  );
  const containerRef = useRef<HTMLDivElement>(null);
  const actionBarRef = useRef<HTMLDivElement>(null);

  const subItemsPivots = useAtomValue(subItemsPivotsAtom);

  const allSubItemIds = useMemo(
    () =>
      rows.reduce<Array<string | number>>(
        (acc, row) => [
          ...acc,
          ...(
            ((row as Record<string, unknown>)[
              subItems?.getRowProperty() || ''
            ] as Array<{ id: string | number }> | undefined) || []
          ).map(
            ({ id }: { id: string | number }) =>
              `${subItemPrefixKey}_${getId(row)}_${id}`
          )
        ],
        []
      ),
    [rows, subItems, getId]
  );

  const rowsToDisplay = useMemo(
    () =>
      subItems?.enable
        ? rows.reduce<Array<TRow>>((acc, row): Array<TRow> => {
            const rowAsRecord = row as Record<string, unknown>;
            if (
              rowAsRecord[subItems.getRowProperty()] &&
              subItemsPivots.includes(row.id)
            ) {
              return [
                ...acc,
                row,
                ...(rowAsRecord[subItems.getRowProperty()] as Array<TRow>).map(
                  (subRow: TRow) => ({
                    ...subRow,
                    internalListingParentId: row.id,
                    internalListingParentRow: row
                  })
                )
              ];
            }

            return [...acc, row];
          }, [])
        : rows,
    [rows, subItemsPivots, subItems]
  );

  const getSubItemRowId = useCallback((row: TRow) => {
    return `${subItemPrefixKey}_${row.internalListingParentId}_${row.id}`;
  }, []);

  const getIsSubItem = useCallback(
    (row: TRow) => {
      return allSubItemIds.includes(getSubItemRowId(row));
    },
    [allSubItemIds, getSubItemRowId]
  );

  const getRowId = useCallback(
    (row: TRow) => {
      return getIsSubItem(row) ? getSubItemRowId(row) : getId(row);
    },
    [getId, getIsSubItem, getSubItemRowId]
  );
  const { isShiftKeyDown } = useKeyObserver();

  const haveSameId = (row: TRow, rowToCompare: TRow): boolean =>
    equals(getId(row), getId(rowToCompare));

  const selectedRowsInclude = (row: TRow): boolean => {
    return !!selectedRows.find((includedRow) =>
      equals(getId(includedRow), getId(row))
    );
  };

  const selectAllRows = (event: React.ChangeEvent<HTMLInputElement>): void => {
    if (
      event.target.checked &&
      event.target.getAttribute('data-indeterminate') === 'false'
    ) {
      onSelectRows(reject(disableRowCheckCondition, rowsToDisplay));
      setLastSelectionIndex(null);

      return;
    }

    onSelectRows([]);
    setLastSelectionIndex(null);
  };

  const onSelectRowsWithCondition = (
    condition: (row: TRow) => boolean
  ): void => {
    onSelectRows(reject(disableRowCheckCondition, filter(condition, rows)));
    setLastSelectionIndex(null);
  };

  interface GetSelectedRowsWithShiftKeyProps {
    compareFunction: (a: number, b: number) => boolean;
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
      compareFunction(
        lastSelectionIndex as number,
        last(selectedRowsIndex) as number
      )
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
      const selectedRowsFromTheStart = slice(
        0,
        selectedRowIndex + 1,
        rowsToDisplay
      );

      onSelectRows(reject(disableRowCheckCondition, selectedRowsFromTheStart));

      return;
    }

    const selectedRowsIndex = map(
      (row) =>
        findIndex(
          (listingRow) => equals(getId(row), getId(listingRow)),
          rowsToDisplay
        ),
      selectedRows
    ).sort(subtract);

    if (selectedRowIndex < lastSelectedIndex) {
      const newSelection = slice(
        selectedRowIndex,
        (lastSelectionIndex as number) + 1,
        rowsToDisplay
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

    const newSelection = slice(
      lastSelectedIndex,
      selectedRowIndex + 1,
      rowsToDisplay
    );
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

  const selectRow = (event: React.MouseEvent, row: TRow): void => {
    event.preventDefault();
    event.stopPropagation();
    // This prevents unwanted text selection
    document.getSelection()?.removeAllRanges();

    const selectedRowIndex = findIndex(
      (listingRow) => equals(getId(row), getId(listingRow)),
      rowsToDisplay
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

  const hoverRow = (row: TRow): void => {
    if (equals(hoveredRowId, getRowId(row))) {
      return;
    }
    setHoveredRowId(getRowId(row));
  };

  const clearHoveredRow = (): void => {
    setHoveredRowId(null);
  };

  const isSelected = (row: TRow): boolean => {
    return selectedRowsInclude(row);
  };

  const changeLimit = (updatedLimit: string | number): void => {
    onLimitChange?.(Number(updatedLimit));
  };

  const visibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });

  useEffect(() => {
    if (not(isShiftKeyDown)) {
      setShiftKeyDownRowPivot(null);

      return;
    }
    setShiftKeyDownRowPivot(lastSelectionIndex);
  }, [isShiftKeyDown, lastSelectionIndex]);

  const areColumnsEditable = not(isNil(onSelectColumns));

  const disableRowConditionForCell = disableRowCondition as (
    row: Record<string, unknown>
  ) => boolean;
  const getHighlightRowConditionForCell = getHighlightRowCondition as
    | ((row: Record<string, unknown>) => boolean)
    | undefined;

  return (
    <div className="h-full w-full overflow-hidden">
      {loading && rows.length > 0 ? (
        <LinearProgress className="w-full h-[1px]" />
      ) : (
        <Divider />
      )}
      <div
        className="bg-[none] flex flex-col h-full w-full"
        ref={containerRef as RefObject<HTMLDivElement>}
      >
        {isActionBarVisible && (
          <div
            className="flex items-center"
            ref={actionBarRef as RefObject<HTMLDivElement>}
          >
            <ListingActionBar
              actions={actions}
              actionsBarMemoProps={actionsBarMemoProps}
              approximateTotalRows={approximateTotalRows}
              columnConfiguration={columnConfiguration}
              columns={columns}
              currentPage={currentPage}
              customPaginationClassName={customPaginationClassName}
              isApproximateCountLoading={isApproximateCountLoading}
              limit={limit}
              listingVariant={listingVariant}
              moveTablePagination={moveTablePagination}
              onApproximateCountClick={onApproximateCountClick}
              onLimitChange={changeLimit}
              onPaginate={onPaginate}
              onResetColumns={onResetColumns}
              onSelectColumns={onSelectColumns}
              paginated={paginated}
              totalRows={totalRows}
              viewerModeConfiguration={viewerModeConfiguration}
              widthToMoveTablePagination={widthToMoveTablePagination}
            />
          </div>
        )}

        <ParentSize
          parentSizeStyles={{
            height: '100%',
            overflowY: 'auto',
            width: '100%'
          }}
        >
          {({ height }) => (
            <Box
              className="border-b-[none] overflow-auto"
              component="div"
              style={{
                height: innerScrollDisabled ? '100%' : `calc(${height}px - 4px)`
              }}
            >
              {displayCustomListing ? (
                customListingComponent
              ) : (
                <Table
                  className="grid items-center relative"
                  component="div"
                  size="small"
                  stickyHeader
                  style={{
                    gridTemplateColumns: gridTemplateColumn,
                    gridTemplateRows: `${dataStyle.header.height}px repeat(${
                      rowsToDisplay.length || 1
                    }, ${isResponsive ? 'auto' : `${dataStyle.body.height}px`})`
                  }}
                >
                  <ListingHeader
                    areColumnsEditable={areColumnsEditable}
                    checkable={checkable}
                    columnConfiguration={columnConfiguration}
                    columns={columns}
                    listingVariant={listingVariant}
                    memoProps={headerMemoProps}
                    onSelectAllClick={selectAllRows}
                    onSelectColumns={onSelectColumns}
                    onSelectRowsWithCondition={onSelectRowsWithCondition}
                    onSort={onSort}
                    predefinedRowsSelection={predefinedRowsSelection}
                    rowCount={rowsToDisplay.length}
                    selectedRowCount={selectedRows.length}
                    sortField={sortField}
                    sortOrder={sortOrder}
                  />

                  <TableBody
                    className="contents relative"
                    component="div"
                    onMouseLeave={clearHoveredRow}
                  >
                    {rowsToDisplay.map((row) => {
                      const isRowSelected = isSelected(row);
                      const isSubItem = allSubItemIds.includes(
                        getSubItemRowId(row)
                      );
                      const isRowHovered = equals(hoveredRowId, getRowId(row));

                      return (
                        <ListingRow
                          checkable={
                            checkable &&
                            (!isSubItem || subItems.canCheckSubItems)
                          }
                          columnConfiguration={columnConfiguration}
                          columnIds={columns.map(prop('id'))}
                          disableRowCondition={disableRowConditionForCell}
                          isHovered={isRowHovered}
                          isSelected={isRowSelected}
                          isShiftKeyDown={isShiftKeyDown}
                          key={getRowId(row)}
                          lastSelectionIndex={lastSelectionIndex}
                          limit={limit}
                          listingVariant={listingVariant}
                          onClick={
                            isSubItem
                              ? undefined
                              : (): void => {
                                  onRowClick(row);
                                }
                          }
                          onFocus={(): void => hoverRow(row)}
                          onMouseOver={(): void => hoverRow(row)}
                          row={row}
                          rowColorConditions={rowColorConditions}
                          shiftKeyDownRowPivot={shiftKeyDownRowPivot}
                          subItemsPivots={subItemsPivots}
                          tabIndex={-1}
                          visibleColumns={visibleColumns}
                        >
                          {checkable &&
                            (!isSubItem || subItems.canCheckSubItems ? (
                              <Cell
                                align="left"
                                className="justify-start"
                                disableRowCondition={disableRowConditionForCell}
                                isRowHovered={isRowHovered}
                                onClick={(event): void => selectRow(event, row)}
                                row={row}
                                rowColorConditions={rowColorConditions}
                              >
                                <Checkbox
                                  checked={isRowSelected}
                                  className="pl-1"
                                  disabled={
                                    disableRowCheckCondition(row) ||
                                    disableRowCondition(row)
                                  }
                                  slotProps={{
                                    input: {
                                      'aria-label': `Select row ${getId(row)}`
                                    }
                                  }}
                                />
                              </Cell>
                            ) : (
                              <Cell
                                align="left"
                                disableRowCondition={disableRowConditionForCell}
                                isRowHovered={isRowHovered}
                                row={row}
                                rowColorConditions={rowColorConditions}
                              />
                            ))}

                          {visibleColumns.map((column) => (
                            <DataCell
                              column={column}
                              disableRowCondition={disableRowConditionForCell}
                              getHighlightRowCondition={
                                getHighlightRowConditionForCell
                              }
                              isRowHovered={isRowHovered}
                              isRowSelected={isRowSelected}
                              key={`${getId(row)}-${column.id}`}
                              labelCollapse={subItems.labelCollapse}
                              labelExpand={subItems.labelExpand}
                              listingVariant={listingVariant}
                              row={row}
                              rowColorConditions={rowColorConditions}
                              subItemsRowProperty={subItems?.getRowProperty(
                                row
                              )}
                            />
                          ))}
                        </ListingRow>
                      );
                    })}

                    {rows.length < 1 &&
                      (loading ? (
                        <SkeletonLoader rows={limit} />
                      ) : (
                        <EmptyResult
                          label={
                            labelNoResultFound
                              ? typeof labelNoResultFound === 'string'
                                ? t(labelNoResultFound)
                                : labelNoResultFound
                              : t(defaultLabelNoResultFound)
                          }
                        />
                      ))}
                  </TableBody>
                </Table>
              )}
            </Box>
          )}
        </ParentSize>
      </div>
    </div>
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
  listingVariant,
  labelNoResultFound,
  approximateTotalRows,
  onApproximateCountClick,
  isApproximateCountLoading,
  ...props
}: MemoizedListingProps<TRow>): JSX.Element =>
  useMemoComponent({
    Component: (
      <Listing
        approximateTotalRows={approximateTotalRows}
        checkable={checkable}
        columnConfiguration={columnConfiguration}
        columns={columns}
        currentPage={currentPage}
        innerScrollDisabled={innerScrollDisabled}
        isApproximateCountLoading={isApproximateCountLoading}
        labelNoResultFound={labelNoResultFound}
        limit={limit}
        listingVariant={listingVariant}
        loading={loading}
        moveTablePagination={moveTablePagination}
        onApproximateCountClick={onApproximateCountClick}
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
      columns.map(
        pick(['id', 'label', 'disabled', 'width', 'shortLabel', 'sortField'])
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
      listingVariant,
      labelNoResultFound,
      approximateTotalRows,
      isApproximateCountLoading
    ]
  });

export default Listing;
export { getVisibleColumns };
