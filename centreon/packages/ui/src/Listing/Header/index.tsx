import * as React from 'react';

import { closestCenter, DraggableSyntheticListeners } from '@dnd-kit/core';
import { horizontalListSortingStrategy } from '@dnd-kit/sortable';
import { equals, find, isEmpty, map, not, pick, propEq } from 'ramda';
import { makeStyles, withStyles } from 'tss-react/mui';

import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import { TableCell, TableHead, TableRow } from '@mui/material';

import { getVisibleColumns, Props as ListingProps } from '..';
import PopoverMenu from '../../PopoverMenu';
import SortableItems from '../../SortableItems';
import Checkbox from '../Checkbox';
import { Column, HeaderTable, PredefinedRowSelection } from '../models';
import { labelPredefinedRowsSelectionMenu } from '../translatedLabels';
import useStyleTable from '../useStyleTable';

import PredefinedSelectionList from './PredefinedSelectionList';
import SortableHeaderCellContent from './SortableCell/Content';

const height = 28;

const HeaderCell = withStyles(TableCell, (theme) => ({
  root: {
    padding: theme.spacing(0)
  }
}));

interface StylesProps {
  headerData: HeaderTable;
}

const useStyles = makeStyles<StylesProps>()((theme, { headerData }) => ({
  CheckboxHeaderCell: {
    alignItems: 'center',
    borderBottom: 'none',
    display: 'flex',
    justifyContent: 'end',
    minWidth: theme.spacing(51 / 8)
  },
  checkBox: {
    color: headerData.color
  },
  compactCell: {
    paddingLeft: theme.spacing(0.5)
  },

  headerLabelDragging: {
    cursor: 'grabbing'
  },
  predefinedRowsMenu: {
    color: headerData.color,
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
  const { headerData } = useStyleTable({});
  const { classes, cx } = useStyles({ headerData });

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
    <TableHead className={classes.row}>
      <TableRow className={classes.row} component="div">
        {checkable && (
          <TableCell className={cx(classes.CheckboxHeaderCell)} component="div">
            <Checkbox
              checked={selectedRowCount === rowCount}
              className={classes.checkBox}
              indeterminate={
                selectedRowCount > 0 && selectedRowCount < rowCount
              }
              inputProps={{ 'aria-label': 'Select all' }}
              onChange={onSelectAllClick}
              // className={classes.compactCell}
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
          </TableCell>
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
