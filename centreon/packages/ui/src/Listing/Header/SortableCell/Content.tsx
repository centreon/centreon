import * as React from 'react';

import { always, and, equals, ifElse, isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { TableCellBaseProps, TableSortLabel, Tooltip } from '@mui/material';
import DragIndicatorIcon from '@mui/icons-material/MoreVert';

import { Props as ListingProps } from '../..';
import { Column } from '../../models';
import HeaderLabel from '../Label';
import { HeaderCell } from '..';
import { useStyles as useCellStyles } from '../../Cell/DataCell';

type StylesProps = Pick<Props, 'isDragging' | 'isInDragOverlay'>;

const useStyles = makeStyles<StylesProps>()(
  (theme, { isDragging, isInDragOverlay }) => ({
    content: {
      alignItems: 'center',
      display: 'flex',
      minHeight: theme.spacing(3),
      padding: theme.spacing(0, 1.5)
    },
    dragHandle: {
      alignSelf: 'flex-start',
      cursor: isDragging ? 'grabbing' : 'grab',
      display: 'flex',
      marginLeft: -theme.spacing(1),
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
  const { classes: cellClasses } = useCellStyles();

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
      <div>
        <HeaderLabel>{columnLabel}</HeaderLabel>
      </div>
    </Tooltip>
  );

  return (
    <HeaderCell
      className={cx([cellClasses.cell, classes.item])}
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
      padding={column.compact ? 'none' : 'normal'}
    >
      <div className={classes.content} ref={itemRef} style={style}>
        {columnConfiguration?.sortable && (
          <div
            className={classes.dragHandle}
            {...props}
            aria-label={columnLabel}
          >
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
            {headerContent}
          </TableSortLabel>
        ) : (
          headerContent
        )}
      </div>
    </HeaderCell>
  );
};

export default SortableHeaderCellContent;
