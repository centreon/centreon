import * as React from 'react';

import {
  equals,
  find,
  indexOf,
  isEmpty,
  isNil,
  map,
  move,
  not,
  path,
  pick,
  prop,
  propEq,
} from 'ramda';
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import { SortableContext } from '@dnd-kit/sortable';

import {
  TableHead,
  TableRow,
  TableCell,
  withStyles,
  makeStyles,
} from '@material-ui/core';
import ArrowDropDownIcon from '@material-ui/icons/ArrowDropDown';

import Checkbox from '../Checkbox';
import { getVisibleColumns, Props as ListingProps } from '..';
import { Column, PredefinedRowSelection } from '../models';
import PopoverMenu from '../../PopoverMenu';

import SortableHeaderCell from './SortableCell';
import SortableHeaderCellContent from './SortableCell/Content';
import PredefinedSelectionList from './PredefinedSelectionList';

const height = 28;

const HeaderCell = withStyles((theme) => ({
  root: {
    backgroundColor: theme.palette.common.white,
    height,
    padding: theme.spacing(0, 0, 0, 1.5),
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
  onSelectAllClick: (event) => void;
  onSelectRowsWithCondition: (condition) => void;
  predefinedRowsSelection: Array<PredefinedRowSelection>;
  rowCount: number;
  selectedRowCount: number;
};

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
}: Props): JSX.Element => {
  const classes = useStyles();

  const sensors = useSensors(useSensor(PointerSensor));

  const visibleColumns = getVisibleColumns({
    columnConfiguration,
    columns,
  });

  const visibleColumnIds = visibleColumns.map(prop('id'));

  const [draggingColumnId, setDraggingColumnId] = React.useState<string>();

  const startDrag = (event) => {
    setDraggingColumnId(path<string>(['active', 'id'], event));
  };

  const cancelDrag = () => {
    setDraggingColumnId(undefined);
  };

  const endDrag = ({ over }) => {
    if (isNil(over)) {
      return;
    }

    const { id } = over;

    const fromIndex = indexOf(draggingColumnId, visibleColumnIds);
    const toIndex = indexOf(id, visibleColumnIds);

    const updatedColumnIds = move(fromIndex, toIndex, visibleColumnIds);

    onSelectColumns?.(updatedColumnIds);
    setDraggingColumnId(undefined);
  };

  const getColumnById = (id: string): Column => {
    return find(propEq('id', id), columns) as Column;
  };

  return (
    <>
      <DndContext
        sensors={sensors}
        onDragCancel={cancelDrag}
        onDragEnd={endDrag}
        onDragStart={startDrag}
      >
        <TableHead className={classes.row} component="div">
          <TableRow className={classes.row} component="div">
            {checkable && (
              <CheckboxHeaderCell component="div">
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
                    {({ close }) => (
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

            <SortableContext items={visibleColumnIds}>
              {visibleColumns.map((column) => (
                <SortableHeaderCell
                  column={column}
                  columnConfiguration={columnConfiguration}
                  key={column.id}
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={onSort}
                />
              ))}
            </SortableContext>
          </TableRow>
        </TableHead>
        <DragOverlay>
          {draggingColumnId && (
            <SortableHeaderCellContent
              isDragging
              column={getColumnById(draggingColumnId)}
              columnConfiguration={columnConfiguration}
            />
          )}
        </DragOverlay>
      </DndContext>
    </>
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

const MemoizedListingHeader = React.memo(
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
    equals(prevProps.columnConfiguration, nextProps.columnConfiguration),
);

export default MemoizedListingHeader;
export { height as headerHeight, HeaderCell };
