/* eslint-disable react/no-array-index-key */

import { useRef } from 'react';

import { equals, gte, pick, prop } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, LinearProgress, Table, TableBody } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { ParentSize, useMemoComponent } from '../..';

import { getVisibleColumns, performanceRowsLimit } from './utils';
import Cell from './Cell';
import DataCell from './Cell/DataCell';
import Checkbox from './Checkbox';
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
import useStyleTable from './useStyleTable';
import { useListingStyles } from './Listing.styles';
import { EmptyResult } from './EmptyResult/EmptyResult';
import { SkeletonLoader } from './Row/SkeletonLoaderRows';
import { ListingHeader } from './Header';
import useListing from './useListing';

export interface Props<TRow> {
  checkable?: boolean;
  columnConfiguration?: ColumnConfiguration;
  columns: Array<Column>;
  currentPage?: number;
  customListingComponent?: JSX.Element;
  disableRowCheckCondition?: (row) => boolean;
  disableRowCondition?: (row) => boolean;
  displayCustomListing?: boolean;
  getHighlightRowCondition?: (row: TRow) => boolean;
  getId?: (row: TRow) => RowId;
  headerMemoProps?: Array<unknown>;
  innerScrollDisabled?: boolean;
  limit?: number;
  listingVariant?: ListingVariant;
  loading?: boolean;
  onRowClick?: (row: TRow) => void;
  onSelectColumns?: (selectedColumnIds: Array<string>) => void;
  onSelectRows?: (rows: Array<TRow>) => void;
  onSort?: (sortParams: { sortField: string; sortOrder: SortOrder }) => void;
  predefinedRowsSelection?: Array<PredefinedRowSelection>;
  rowColorConditions?: Array<RowColorCondition>;
  rows?: Array<TRow>;
  selectedRows?: Array<TRow>;
  sortField?: string;
  sortOrder?: SortOrder;
  subItems?: {
    canCheckSubItems: boolean;
    enable: boolean;
    getRowProperty: (row?) => string;
    labelCollapse: string;
    labelExpand: string;
  };
  totalRows?: number;
}

const defaultColumnConfiguration = {
  sortable: false
};

const Listing = <TRow extends { id: RowId }>({
  customListingComponent,
  displayCustomListing,
  limit = 10,
  columns,
  columnConfiguration = defaultColumnConfiguration,
  onSelectColumns,
  rows = [],
  currentPage = 0,
  totalRows = 0,
  checkable = false,
  rowColorConditions = [],
  loading = false,
  selectedRows = [],
  sortOrder = undefined,
  sortField = undefined,
  innerScrollDisabled = false,
  disableRowCheckCondition = (): boolean => false,
  disableRowCondition = (): boolean => false,
  onRowClick = (): void => undefined,
  onSelectRows = (): void => undefined,
  onSort,
  getId = ({ id }): RowId => id,
  headerMemoProps = [],
  predefinedRowsSelection = [],
  listingVariant = ListingVariant.compact,
  getHighlightRowCondition,
  subItems = {
    canCheckSubItems: false,
    enable: false,
    getRowProperty: () => '',
    labelCollapse: 'Collapse',
    labelExpand: 'Expand'
  }
}: Props<TRow>): JSX.Element => {
  const { t } = useTranslation();

  const containerRef = useRef<HTMLDivElement>();

  const currentVisibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });

  const visibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });

  const {
    allSubItemIds,
    areColumnsEditable,
    clearHoveredRow,
    emptyRows,
    hoverRow,
    isSelected,
    onSelectRowsWithCondition,
    rowsToDisplay,
    selectAllRows,
    selectRow,
    isShiftKeyDown,
    hoveredRowId,
    lastSelectionIndex,
    shiftKeyDownRowPivot,
    subItemsPivots
  } = useListing({
    currentPage,
    disableRowCheckCondition,
    getId,
    limit,
    onSelectColumns,
    onSelectRows,
    rows,
    selectedRows,
    subItems,
    totalRows
  });
  const { dataStyle, getGridTemplateColumn } = useStyleTable({
    checkable,
    currentVisibleColumns,
    listingVariant
  });

  const { classes } = useListingStyles({
    dataStyle,
    getGridTemplateColumn,
    listingVariant,
    rows: rowsToDisplay
  });

  return (
    <div className={classes.listingContainer}>
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
        {/* <div
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
            listingVariant={listingVariant}
            moveTablePagination={moveTablePagination}
            paginated={paginated}
            totalRows={totalRows}
            viewerModeConfiguration={viewerModeConfiguration}
            visualizationActions={visualizationActions}
            widthToMoveTablePagination={widthToMoveTablePagination}
            onLimitChange={changeLimit}
            onPaginate={onPaginate}
            onResetColumns={onResetColumns}
            onSelectColumns={onSelectColumns}
          />
        </div> */}

        <ParentSize
          parentSizeStyles={{
            height: '100%',
            overflowY: 'auto',
            width: '100%'
          }}
        >
          {({ height }) => (
            <Box
              className={classes.tableWrapper}
              component="div"
              style={{
                height: innerScrollDisabled ? '100%' : `calc(${height}px - 4px)`
              }}
            >
              {displayCustomListing ? (
                customListingComponent
              ) : (
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
                    listingVariant={listingVariant}
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
                    component="div"
                    onMouseLeave={clearHoveredRow}
                  >
                    {rowsToDisplay.map((row, index) => {
                      const isRowSelected = isSelected(row);
                      const isRowHovered = equals(hoveredRowId, getId(row));
                      const isSubItem = allSubItemIds.includes(row.id);

                      return (
                        <ListingRow
                          checkable={
                            checkable &&
                            (!isSubItem || subItems.canCheckSubItems)
                          }
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
                          listingVariant={listingVariant}
                          row={row}
                          rowColorConditions={rowColorConditions}
                          shiftKeyDownRowPivot={shiftKeyDownRowPivot}
                          subItemsPivots={subItemsPivots}
                          tabIndex={-1}
                          visibleColumns={visibleColumns}
                          onClick={(): void => {
                            onRowClick(row);
                          }}
                          onFocus={(): void => hoverRow(row)}
                          onMouseOver={(): void => hoverRow(row)}
                        >
                          {checkable &&
                            (!isSubItem || subItems.canCheckSubItems ? (
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
                            ) : (
                              <Cell
                                align="left"
                                disableRowCondition={disableRowCondition}
                                isRowHovered={isRowHovered}
                                row={row}
                                rowColorConditions={rowColorConditions}
                              />
                            ))}

                          {visibleColumns.map((column) => (
                            <DataCell
                              column={column}
                              disableRowCondition={disableRowCondition}
                              getHighlightRowCondition={
                                getHighlightRowCondition
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
                        <EmptyResult label={t(labelNoResultFound)} />
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
  selectedRows = [],
  sortOrder = undefined,
  sortField = undefined,
  innerScrollDisabled = false,
  columnConfiguration,
  listingVariant,
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
        listingVariant={listingVariant}
        loading={loading}
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
      pick(
        ['id', 'label', 'disabled', 'width', 'shortLabel', 'sortField'],
        columns
      ),
      columnConfiguration,
      limit,
      rows,
      currentPage,
      totalRows,
      checkable,
      loading,
      // paginated,
      selectedRows,
      sortOrder,
      sortField,
      innerScrollDisabled,
      listingVariant
    ]
  });

export default Listing;
