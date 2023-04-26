import * as React from 'react';

import { closestCenter, DraggableSyntheticListeners } from '@dnd-kit/core';
import { horizontalListSortingStrategy } from '@dnd-kit/sortable';
import { equals, find, map, pick, propEq } from 'ramda';

import { TableHead, TableRow } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { getVisibleColumns, Props as ListingProps } from '..';
import SortableItems from '../../SortableItems';
import { Column } from '../models';

import ListingHeaderCell from './Cell/ListingHeaderCell';
import { useStyles } from './ListingHeader.styles';
import {
  SelectActionListingHeaderCell,
  SelectActionListingHeaderCellProps
} from './Cell/SelectActionListingHeaderCell';

type Props = Pick<
  ListingProps<unknown>,
  | 'sortField'
  | 'sortOrder'
  | 'onSort'
  | 'columns'
  | 'checkable'
  | 'onSelectColumns'
  | 'columnConfiguration'
  | 'totalRows'
> & {
  areColumnsEditable: boolean;
  memoProps: Array<unknown>;
  rowCount: number;
  viewMode?: ListingVariant;
} & SelectActionListingHeaderCellProps;

interface ContentProps extends Pick<Props, 'sortField' | 'sortOrder'> {
  attributes;
  id: string;
  isDragging: boolean;
  isInDragOverlay?: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style;
}

const ListingHeader = ({
  sortOrder,
  sortField,
  rowCount,
  columns,
  columnConfiguration,
  onSort,
  onSelectColumns,
  checkable,
  memoProps,
  areColumnsEditable,
  viewMode,
  onSelectAllClick,
  selectedRowCount,
  predefinedRowsSelection,
  onSelectRowsWithCondition
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  const visibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });

  const getColumnById = (id: string): Column => {
    return find(propEq('id', id), columns) as Column;
  };

  const Content = React.useCallback(
    ({
      isInDragOverlay,
      listeners,
      attributes,
      style,
      isDragging,
      itemRef,
      id
    }: ContentProps): JSX.Element => {
      return (
        <ListingHeaderCell
          areColumnsEditable={areColumnsEditable}
          column={getColumnById(id)}
          columnConfiguration={columnConfiguration}
          isDragging={isDragging}
          isInDragOverlay={isInDragOverlay}
          itemRef={itemRef}
          sortField={sortField}
          sortOrder={sortOrder}
          style={style}
          viewMode={viewMode}
          onSort={onSort}
          {...listeners}
          {...attributes}
        />
      );
    },
    [columnConfiguration, columns, sortField, sortOrder]
  );

  return (
    <TableHead className={cx(classes.row, 'listingHeader')} component="div">
      <TableRow className={classes.row} component="div">
        {checkable && (
          <SelectActionListingHeaderCell
            predefinedRowsSelection={predefinedRowsSelection}
            rowCount={rowCount}
            selectedRowCount={selectedRowCount}
            onSelectAllClick={onSelectAllClick}
            onSelectRowsWithCondition={onSelectRowsWithCondition}
          />
        )}
        <SortableItems
          updateSortableItemsOnItemsChange
          Content={Content}
          additionalProps={[sortField, sortOrder]}
          collisionDetection={closestCenter}
          itemProps={['id']}
          items={visibleColumns}
          memoProps={memoProps}
          sortingStrategy={horizontalListSortingStrategy}
          onDragEnd={({ items }): void => {
            onSelectColumns?.(items);
          }}
        />
      </TableRow>
    </TableHead>
  );
};

const columnMemoProps = [
  'id',
  'label',
  'rowMemoProps',
  'sortField',
  'sortable',
  'type'
];

const MemoizedListingHeader = React.memo<Props>(
  ListingHeader,
  (prevProps, nextProps) =>
    equals(prevProps.sortOrder, nextProps.sortOrder) &&
    equals(prevProps.sortField, nextProps.sortField) &&
    equals(prevProps.selectedRowCount, nextProps.selectedRowCount) &&
    equals(prevProps.rowCount, nextProps.rowCount) &&
    equals(
      map(pick(columnMemoProps), prevProps.columns),
      map(pick(columnMemoProps), nextProps.columns)
    ) &&
    equals(prevProps.checkable, nextProps.checkable) &&
    equals(prevProps.columnConfiguration, nextProps.columnConfiguration) &&
    equals(prevProps.memoProps, nextProps.memoProps) &&
    equals(prevProps.areColumnsEditable, nextProps.areColumnsEditable) &&
    equals(prevProps.viewMode, nextProps.viewMode)
);

export { MemoizedListingHeader as ListingHeader };
