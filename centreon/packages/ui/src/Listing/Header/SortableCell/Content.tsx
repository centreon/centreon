import * as React from 'react';

import { always, and, equals, ifElse, isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import {
  TableCell,
  TableCellBaseProps,
  TableSortLabel,
  Tooltip
} from '@mui/material';

import { Props as ListingProps } from '../..';
import { Column } from '../../models';
import HeaderLabel from '../Label';

import DraggableIcon from './DraggableIcon';

type StylesProps = Pick<Props, 'isDragging' | 'isInDragOverlay'>;

const useStyles = makeStyles<StylesProps>()(
  (theme, { isDragging, isInDragOverlay }) => ({
    active: {
      '&.Mui-active': {
        '& .MuiTableSortLabel-icon': {
          color: theme.palette.common.white
        },
        color: theme.palette.common.white
      },
      '&:hover': {
        '& .MuiTableSortLabel-icon': {
          opacity: 1
        },
        color: theme.palette.common.white
      }
    },
    content: {
      alignItems: 'center',
      borderRadius: isDragging && isInDragOverlay ? theme.spacing(0.5) : 0,
      color: theme.palette.common.white,
      display: 'flex',
      minHeight: theme.spacing(3)
    },
    dragHandle: {
      alignSelf: 'center',
      cursor: isDragging ? 'grabbing' : 'grab',
      display: 'flex',
      outline: 'none'
    },
    simpleHeaderCellContent: {
      alignItems: 'center',
      display: 'inline-flex',
      marginRight: theme.spacing(2)
    },
    tableCell: {
      backgroundColor: isInDragOverlay
        ? 'transparent'
        : theme.palette.background.listingHeader,
      borderBottom: 'none',
      padding: 0
    }
  })
);

type Props = Pick<
  ListingProps<unknown>,
  'columnConfiguration' | 'sortField' | 'sortOrder' | 'onSort'
> & {
  areColumnsEditable: boolean;
  className: string;
  column: Column;
  isDragging?: boolean;
  isInDragOverlay?: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  style;
};

const SortableHeaderCellContent = ({
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
  ...props
}: Props): JSX.Element => {
  const { classes } = useStyles({
    isDragging,
    isInDragOverlay
  });
  const [cellHovered, setCellHovered] = React.useState(false);

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
  };

  const mouseOut = (): void => {
    setCellHovered(false);
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
      ref={itemRef}
      style={style}
      onMouseOut={mouseOut}
      onMouseOver={mouseOver}
    >
      <div className={classes.content}>
        {(!cellHovered || !areColumnsEditable) && <DraggableIcon />}
        {columnConfiguration?.sortable && areColumnsEditable && cellHovered && (
          <DraggableIcon
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
            className={classes.active}
            direction={sortOrder || 'desc'}
            onClick={sort}
          >
            {headerContent}
          </TableSortLabel>
        ) : (
          <div className={classes.simpleHeaderCellContent}>{headerContent}</div>
        )}
      </div>
    </TableCell>
  );
};

export default SortableHeaderCellContent;
