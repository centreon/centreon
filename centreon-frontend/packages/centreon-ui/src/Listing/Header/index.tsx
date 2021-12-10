import * as React from 'react';

import { equals, find, isEmpty, map, not, pick, propEq } from 'ramda';
import { DraggableSyntheticListeners, rectIntersection } from '@dnd-kit/core';
import { rectSortingStrategy } from '@dnd-kit/sortable';

import {
  TableHead,
  TableRow,
  TableCell,
  withStyles,
  makeStyles,
  TableCellBaseProps,
} from '@material-ui/core';
import ArrowDropDownIcon from '@material-ui/icons/ArrowDropDown';

import Checkbox from '../Checkbox';
import { getVisibleColumns, Props as ListingProps } from '..';
import { Column, PredefinedRowSelection } from '../models';
import PopoverMenu from '../../PopoverMenu';
import SortableItems from '../../SortableItems';

import SortableHeaderCellContent from './SortableCell/Content';
import PredefinedSelectionList from './PredefinedSelectionList';

const height = 28;

const HeaderCell = withStyles((theme) => ({
  root: {
    height,
    padding: theme.spacing(0),
  },
}))(TableCell);

const CheckboxHeaderCell = withStyles((theme) => ({
  root: {
    backgroundColor: theme.palette.common.white,
    display: 'grid',
    gridTemplateColumns: 'repeat(2, min-content)',
    height,
    padding: theme.spacing(0, 0, 0, 0.5),
  },
}))(TableCell);

const useStyles = makeStyles((theme) => ({
  compactCell: {
    paddingLeft: theme.spacing(0.5),
  },
  headerLabelDragging: {
    cursor: 'grabbing',
  },
  predefinedRowsMenu: {
    width: theme.spacing(2),
  },
  row: {
    display: 'contents',
  },
}));

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
  memoProps: Array<unknown>;
  onSelectAllClick: (event) => void;
  onSelectRowsWithCondition: (condition) => void;
  predefinedRowsSelection: Array<PredefinedRowSelection>;
  rowCount: number;
  selectedRowCount: number;
};

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
  onSelectAllClick,
  sortOrder,
  sortField,
  selectedRowCount,
  rowCount,
  columns,
  columnConfiguration,
  onSort,
  onSelectColumns,
  checkable,
  predefinedRowsSelection,
  onSelectRowsWithCondition,
  memoProps,
}: Props): JSX.Element => {
  const classes = useStyles();

  const visibleColumns = getVisibleColumns({
    columnConfiguration,
    columns,
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
      id,
    }: ContentProps): JSX.Element => {
      return (
        <SortableHeaderCellContent
          column={getColumnById(id)}
          columnConfiguration={columnConfiguration}
          isDragging={isDragging}
          isInDragOverlay={isInDragOverlay}
          itemRef={itemRef}
          sortField={sortField}
          sortOrder={sortOrder}
          style={style}
          onSort={onSort}
          {...listeners}
          {...attributes}
        />
      );
    },
    [columnConfiguration, columns, sortField, sortOrder],
  );

  return (
    <TableHead className={classes.row} component="div">
      <TableRow className={classes.row} component="div">
        {checkable && (
          <CheckboxHeaderCell
            component={
              'div' as unknown as React.ElementType<TableCellBaseProps>
            }
          >
            <Checkbox
              checked={selectedRowCount === rowCount}
              className={classes.compactCell}
              indeterminate={
                selectedRowCount > 0 && selectedRowCount < rowCount
              }
              inputProps={{ 'aria-label': 'Select all' }}
              onChange={onSelectAllClick}
            />
            {not(isEmpty(predefinedRowsSelection)) && (
              <PopoverMenu
                className={classes.predefinedRowsMenu}
                icon={<ArrowDropDownIcon />}
              >
                {({ close }): JSX.Element => (
                  <PredefinedSelectionList
                    close={close}
                    predefinedRowsSelection={predefinedRowsSelection}
                    onSelectRowsWithCondition={onSelectRowsWithCondition}
                  />
                )}
              </PopoverMenu>
            )}
          </CheckboxHeaderCell>
        )}
        <SortableItems
          updateSortableItemsOnItemsChange
          Content={Content}
          additionalProps={[sortField, sortOrder]}
          collisionDetection={rectIntersection}
          itemProps={['id']}
          items={visibleColumns}
          memoProps={memoProps}
          sortingStrategy={rectSortingStrategy}
          onDragOver={(newItems): void => onSelectColumns?.(newItems)}
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
  'type',
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
      map(pick(columnMemoProps), nextProps.columns),
    ) &&
    equals(prevProps.checkable, nextProps.checkable) &&
    equals(prevProps.columnConfiguration, nextProps.columnConfiguration) &&
    equals(prevProps.memoProps, nextProps.memoProps),
);

export default MemoizedListingHeader;
export { height as headerHeight, HeaderCell };
