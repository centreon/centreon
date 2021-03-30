import * as React from 'react';

import { equals, find, indexOf, isNil, move, path, prop, propEq } from 'ramda';
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

import Checkbox from '../Checkbox';
import { getVisibleColumns, Props as ListingProps } from '..';
import { Column } from '../models';

import SortableHeaderCell from './SortableCell';
import SortableHeaderCellContent from './SortableCell/Content';

const height = 28;

const HeaderCell = withStyles((theme) => ({
  root: {
    backgroundColor: theme.palette.common.white,
    padding: theme.spacing(0, 0, 0, 1.5),
    height,
  },
}))(TableCell);

const useStyles = makeStyles((theme) => ({
  row: {
    display: 'contents',
  },
  compactCell: {
    paddingLeft: theme.spacing(0.5),
  },
  headerLabelDragging: {
    cursor: 'grabbing',
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
  selectedRowCount: number;
  rowCount: number;
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
}: Props): JSX.Element => {
  const classes = useStyles();

  const sensors = useSensors(useSensor(PointerSensor));

  const visibleColumns = getVisibleColumns({
    columns,
    columnConfiguration,
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
    <DndContext
      sensors={sensors}
      onDragStart={startDrag}
      onDragCancel={cancelDrag}
      onDragEnd={endDrag}
    >
      <TableHead className={classes.row} component="div">
        <TableRow className={classes.row} component="div">
          {checkable && (
            <HeaderCell component="div">
              <Checkbox
                inputProps={{ 'aria-label': 'Select all' }}
                indeterminate={
                  selectedRowCount > 0 && selectedRowCount < rowCount
                }
                checked={selectedRowCount === rowCount}
                onChange={onSelectAllClick}
              />
            </HeaderCell>
          )}

          <SortableContext items={visibleColumnIds}>
            {visibleColumns.map((column) => (
              <SortableHeaderCell
                key={column.id}
                columnConfiguration={columnConfiguration}
                column={column}
                onSort={onSort}
                sortOrder={sortOrder}
                sortField={sortField}
              />
            ))}
          </SortableContext>
        </TableRow>
      </TableHead>
      <DragOverlay>
        {draggingColumnId && (
          <SortableHeaderCellContent
            column={getColumnById(draggingColumnId)}
            columnConfiguration={columnConfiguration}
            isDragging
          />
        )}
      </DragOverlay>
    </DndContext>
  );
};

const MemoizedListingHeader = React.memo(
  ListingHeader,
  (prevProps, nextProps) =>
    equals(prevProps.sortOrder, nextProps.sortOrder) &&
    equals(prevProps.sortField, nextProps.sortField) &&
    equals(prevProps.selectedRowCount, nextProps.selectedRowCount) &&
    equals(prevProps.rowCount, nextProps.rowCount) &&
    equals(prevProps.columns, nextProps.columns) &&
    equals(prevProps.checkable, nextProps.checkable) &&
    equals(prevProps.columnConfiguration, nextProps.columnConfiguration),
);

export default MemoizedListingHeader;
export { height as headerHeight, HeaderCell };
