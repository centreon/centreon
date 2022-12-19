import * as React from 'react';

import { useUpdateAtom } from 'jotai/utils';
import { always, and, equals, ifElse, isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { TableCellBaseProps, TableSortLabel, Tooltip } from '@mui/material';

import { HeaderCell } from '..';
import { Props as ListingProps } from '../..';
import { useStyles as useCellStyles } from '../../Cell/DataCell';
import { Column } from '../../models';
import { hoveredHeaderAtom } from '../headerAtom';
import HeaderLabel from '../Label';

import DragInvisibleIcon from './DragInvisibleIcon';

type StylesProps = Pick<Props, 'isDragging' | 'isInDragOverlay'>;

const useStyles = makeStyles<StylesProps>()(
  (theme, { isDragging, isInDragOverlay }) => ({
    content: {
      alignItems: 'center',
      display: 'flex',
      minHeight: theme.spacing(3)
    },
    dragHandle: {
      alignSelf: 'center',
      cursor: isDragging ? 'grabbing' : 'grab',
      display: 'flex',
      outline: 'none'
    },

    item: {
      background: isInDragOverlay
        ? 'transparent'
        : theme.palette.background.paper,
      border: isInDragOverlay ? 'none' : undefined,
      borderBottom: isInDragOverlay
        ? 'none'
        : `1px solid ${theme.palette.text.primary}`
    }
  })
);

type Props = Pick<
  ListingProps<unknown>,
  'columnConfiguration' | 'sortField' | 'sortOrder' | 'onSort'
> & {
  column: Column;
  isDragging?: boolean;
  isInDragOverlay?: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  style;
};

const SortableHeaderCellContent = ({
  isInDragOverlay,
  column,
  columnConfiguration,
  sortField,
  sortOrder,
  onSort,
  isDragging,
  itemRef,
  style,
  ...props
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ isDragging, isInDragOverlay });
  const cellClasses = useCellStyles();
  const [cellHovered, setCellHovered] = React.useState(false);

  const setHoveredHeader = useUpdateAtom(hoveredHeaderAtom);

  const columnLabel = column.shortLabel || column.label;

  const columnSortField = column.sortField || column.id;

  const getTooltipLabel = ifElse(isNil, always(''), always(column.label));

  const sort = (): void => {
    const isDesc = and(
      equals(columnSortField, sortField),
      equals(sortOrder, 'desc')
    );

    onSort?.({
      sortField: columnSortField,
      sortOrder: isDesc ? 'asc' : 'desc'
    });
  };

  const mouseOver = (): void => {
    setCellHovered(true);

    setHoveredHeader({ column, isHeaderHovered: true });
  };

  const mouseOut = (): void => {
    setCellHovered(false);
    setHoveredHeader({ column, isHeaderHovered: false });
  };

  const headerContent = (
    <Tooltip placement="top" title={getTooltipLabel(column.shortLabel)}>
      <HeaderLabel>{columnLabel}</HeaderLabel>
    </Tooltip>
  );

  return (
    <HeaderCell
      className={cx([cellClasses.cell, classes.item])}
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
      padding={column.compact ? 'none' : 'normal'}
      onMouseOut={mouseOut}
      onMouseOver={mouseOver}
    >
      <div className={classes.content} ref={itemRef} style={style}>
        {!cellHovered && <DragInvisibleIcon />}
        {columnConfiguration?.sortable && cellHovered && (
          <DragInvisibleIcon
            visible
            {...props}
            aria-label={columnLabel}
            className={classes.dragHandle}
          />
        )}

        {column.sortable ? (
          <TableSortLabel
            active={sortField === columnSortField}
            aria-label={`Column ${column.label}`}
            direction={sortOrder || 'desc'}
            onClick={sort}
          >
            {headerContent}
          </TableSortLabel>
        ) : (
          <>
            {headerContent}
            <DragInvisibleIcon />
          </>
        )}
      </div>
    </HeaderCell>
  );
};

export default SortableHeaderCellContent;
