import { useRef, useState } from 'react';

import { equals } from 'ramda';

import { ListingVariant } from '@centreon/ui-context';

import { useStyles } from './DataTable.styles';
import { MemoizedListing } from './Listing/Listing';
import { Grid } from './Grid';
import {
  Column,
  ColumnConfiguration,
  PredefinedRowSelection,
  RowColorCondition,
  RowId,
  SortOrder
} from './Listing/models';
import ListingActionBar from './ActionBar';
import CardActionsX from './CardActionsX';

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
  cardActions?: () => JSX.Element;
  checkable?: boolean;
  columnConfiguration?: ColumnConfiguration;
  columns: Array<Column>;
  currentPage?: number;
  customListingComponent?: JSX.Element;
  customPaginationClassName?: string;
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
  subItems?: {
    canCheckSubItems: boolean;
    enable: boolean;
    getRowProperty: (row?) => string;
    labelCollapse: string;
    labelExpand: string;
  };
  totalRows?: number;
  variant?: 'grid' | 'listing';
  viewerModeConfiguration?: ViewerModeConfiguration;
  visualizationActions?: JSX.Element;
  widthToMoveTablePagination?: number;
}

const DataTable = <TRow extends { id: RowId }>(props): JSX.Element => {
  const { classes } = useStyles();

  const actionBarRef = useRef<HTMLDivElement>();

  const [mode, setMode] = useState('List');

  const {
    actionsBarMemoProps,
    rows,
    viewMode = mode,
    onRowClick,
    cardActions,
    actions,
    onPaginate,
    onResetColumns,
    widthToMoveTablePagination,
    onSelectColumns,
    columnConfiguration,
    currentPage,
    moveTablePagination,
    totalRows,
    columns,
    customPaginationClassName,
    limit = 10,
    listingVariant,
    visualizationActions,
    viewerModeConfiguration,
    onLimitChange,
    checkable,
    viewModeConfiguration = { onViewModeChange: setMode }
  } = props;

  const changeLimit = (updatedLimit: string): void => {
    onLimitChange?.(Number(updatedLimit));
  };

  const isListingView = equals(viewMode, 'List');

  return (
    <div data-variant={viewMode}>
      <div
        className={classes.actionBar}
        ref={actionBarRef as React.RefObject<HTMLDivElement>}
      >
        <ListingActionBar
          paginated
          actions={actions}
          actionsBarMemoProps={actionsBarMemoProps}
          columnConfiguration={isListingView ? columnConfiguration : undefined}
          columns={columns}
          currentPage={currentPage}
          customPaginationClassName={customPaginationClassName}
          limit={limit}
          listingVariant={listingVariant}
          moveTablePagination={moveTablePagination}
          totalRows={totalRows}
          viewMode={viewMode}
          viewModeConfiguration={viewModeConfiguration}
          viewerModeConfiguration={
            isListingView ? viewerModeConfiguration : undefined
          }
          visualizationActions={
            isListingView ? visualizationActions : undefined
          }
          widthToMoveTablePagination={widthToMoveTablePagination}
          onLimitChange={changeLimit}
          onPaginate={onPaginate}
          onResetColumns={onResetColumns}
          onSelectColumns={onSelectColumns}
        />
      </div>

      {isListingView ? (
        <div style={{ height: '100vh', width: '100%' }}>
          <MemoizedListing {...props} />
        </div>
      ) : (
        <Grid
          actions={CardActionsX}
          checkable={checkable}
          rows={rows}
          onItemClick={onRowClick}
        />
      )}
    </div>
  );
};

export default DataTable;
