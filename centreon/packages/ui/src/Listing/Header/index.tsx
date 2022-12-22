import * as React from 'react';

import { equals, find, isEmpty, map, not, pick, propEq } from 'ramda';
import { closestCenter, DraggableSyntheticListeners } from '@dnd-kit/core';
import { horizontalListSortingStrategy } from '@dnd-kit/sortable';
import { withStyles, makeStyles } from 'tss-react/mui';

import {
  TableHead,
  TableRow,
  TableCell,
  TableCellBaseProps
} from '@mui/material';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';

import Checkbox from '../Checkbox';
import { getVisibleColumns, Props as ListingProps } from '..';
import { Column, PredefinedRowSelection } from '../models';
import PopoverMenu from '../../PopoverMenu';
import SortableItems from '../../SortableItems';
import { labelPredefinedRowsSelectionMenu } from '../translatedLabels';

import SortableHeaderCellContent from './SortableCell/Content';
import PredefinedSelectionList from './PredefinedSelectionList';

const height = 28;

const HeaderCell = withStyles(TableCell, (theme) => ({
  root: {
    // height,
    padding: theme.spacing(0)
  }
}));

const CheckboxHeaderCell = withStyles(TableCell, (theme) => ({
  root: {
    // backgroundColor: theme.palette.background.paper,
    // borderBottom: `1px solid ${theme.palette.text.primary}`,
    display: 'grid',
    gridTemplateColumns: 'repeat(2, min-content)',
    // height,
    padding: theme.spacing(0, 0, 0, 0.5)
  }
}));

const useStyles = makeStyles()((theme) => ({
  compactCell: {
    paddingLeft: theme.spacing(0.5)
  },
  container: {
    display: 'contents'
    // 'div:nth-child(1)': {
    //   border: 'solid 0.5px',
    //   height: 38
    // }
  },
  headerLabelDragging: {
    cursor: 'grabbing'
  },
  predefinedRowsMenu: {
    width: theme.spacing(2)
  },
  row: {
    display: 'contents'
  }
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
  memoProps
}: Props): JSX.Element => {
  const { classes } = useStyles();

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
    [columnConfiguration, columns, sortField, sortOrder]
  );

  return (
    <TableHead className={classes.container}>
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
                title={labelPredefinedRowsSelectionMenu}
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
    equals(prevProps.memoProps, nextProps.memoProps)
);

export default MemoizedListingHeader;
export { height as headerHeight, HeaderCell };
