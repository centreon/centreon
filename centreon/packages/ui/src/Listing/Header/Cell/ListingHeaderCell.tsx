import { always, and, equals, ifElse, isNil } from 'ramda';

import {
  TableCell,
  TableCellBaseProps,
  TableSortLabel,
  Tooltip
} from '@mui/material';

import { Props as ListingProps } from '../..';
import { Column } from '../../models';
import { DraggableIconButton } from '../_internals/DraggableIconButton';
import HeaderLabel from '../_internals/Label';

import { StylesProps, useStyles } from './ListingHeaderCell.styles';

type Props = Pick<
  ListingProps<unknown>,
  'columnConfiguration' | 'sortField' | 'sortOrder' | 'onSort'
> & {
  areColumnsEditable: boolean;
  className: string;
  column: Column;
  itemRef: React.RefObject<HTMLDivElement>;
  style;
} & StylesProps;

const ListingHeaderCell = ({
  column,
  columnConfiguration,
  sortField,
  sortOrder,
  onSort,
  isDragging,
  itemRef,
  style,
  isInDragOverlay,
  areColumnsEditable,
  listingVariant,
  ...props
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({
    isDragging,
    isInDragOverlay,
    listingVariant
  });
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

  const headerContent = (
    <Tooltip placement="top" title={getTooltipLabel(column.shortLabel)}>
      <HeaderLabel>{columnLabel}</HeaderLabel>
    </Tooltip>
  );

  return (
    <TableCell
      className={classes.tableCell}
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
      data-isdragging={isDragging}
      data-isindragoverlay={isInDragOverlay}
      ref={itemRef}
      style={style}
    >
      <div className={classes.content}>
        {column.sortable ? (
          <TableSortLabel
            active={sortField === columnSortField}
            aria-label={`Column ${column.label}`}
            className={classes.active}
            direction={sortOrder || 'desc'}
            onClick={sort}
          >
            {headerContent}
          </TableSortLabel>
        ) : (
          <div className={classes.simpleHeaderCellContent}>{headerContent}</div>
        )}

        {columnConfiguration?.sortable && areColumnsEditable && (
          <DraggableIconButton
            {...props}
            className={cx(classes.dragHandle, 'dragHandle')}
            columnLabel={columnLabel}
          />
        )}
      </div>
    </TableCell>
  );
};

export default ListingHeaderCell;
