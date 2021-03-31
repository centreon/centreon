import * as React from 'react';

import { and, equals } from 'ramda';

import { makeStyles, TableSortLabel, Theme } from '@material-ui/core';
import DragIndicatorIcon from '@material-ui/icons/DragIndicator';

import { Props as ListingProps } from '../..';
import { Column } from '../../models';
import HeaderLabel from '../Label';

const useStyles = makeStyles<Theme, Pick<Props, 'isDragging'>>(() => ({
  content: {
    alignItems: 'center',
    display: 'flex',
  },
  dragHandle: ({ isDragging }) => ({
    cursor: isDragging ? 'grabbing' : 'grab',
    display: 'flex',
    outline: 'none',
  }),
}));

type Props = Pick<
  ListingProps<unknown>,
  'columnConfiguration' | 'sortField' | 'sortOrder' | 'onSort'
> & {
  column: Column;
  isDragging?: boolean;
};

const SortableHeaderCellContent = React.forwardRef(
  (
    {
      column,
      columnConfiguration,
      sortField,
      sortOrder,
      onSort,
      isDragging,
      ...props
    }: Props,
    ref: React.ForwardedRef<HTMLDivElement>,
  ): JSX.Element => {
    const classes = useStyles({ isDragging });

    const columnSortField = column.sortField || column.id;

    const sort = (): void => {
      const isDesc = and(
        equals(columnSortField, sortField),
        equals(sortOrder, 'desc'),
      );

      onSort?.({
        sortField: columnSortField,
        sortOrder: isDesc ? 'asc' : 'desc',
      });
    };

    return (
      <div className={classes.content} ref={ref}>
        {columnConfiguration?.sortable && (
          <div className={classes.dragHandle} {...props}>
            <DragIndicatorIcon fontSize="small" />
          </div>
        )}

        {column.sortable ? (
          <TableSortLabel
            active={sortField === columnSortField}
            aria-label={`Column ${column.label}`}
            direction={sortOrder || 'desc'}
            onClick={sort}
          >
            <HeaderLabel>{column.label}</HeaderLabel>
          </TableSortLabel>
        ) : (
          <HeaderLabel>{column.label}</HeaderLabel>
        )}
      </div>
    );
  },
);

export default SortableHeaderCellContent;
