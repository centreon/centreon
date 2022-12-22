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
import { Column, HeaderTable } from '../../models';
import useStyleTable from '../../useStyleTable';
import HeaderLabel from '../Label';

import DraggableIcon from './DraggableIconIcon';

type StylesProps = Pick<Props, 'isDragging'> & { headerData: HeaderTable };

const useStyles = makeStyles<StylesProps>()(
  (theme, { isDragging, headerData }) => ({
    active: {
      '&.Mui-active': {
        '& .MuiTableSortLabel-icon': {
          color: headerData.color
        },
        color: headerData.color
      },
      '&:hover': {
        '& .MuiTableSortLabel-icon': {
          opacity: 1
        },
        color: headerData.color
      }
    },
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
    }
  })
);

type Props = Pick<
  ListingProps<unknown>,
  'columnConfiguration' | 'sortField' | 'sortOrder' | 'onSort'
> & {
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
  ...props
}: Props): JSX.Element => {
  const { headerData } = useStyleTable({});
  const { classes } = useStyles({
    headerData,
    isDragging
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
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
      padding={column.compact ? 'none' : 'normal'}
      onMouseOut={mouseOut}
      onMouseOver={mouseOver}
    >
      <div className={classes.content} ref={itemRef} style={style}>
        {!cellHovered && <DraggableIcon />}
        {columnConfiguration?.sortable && cellHovered && (
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
          <>
            {headerContent}
            <DraggableIcon />
          </>
        )}
      </div>
    </TableCell>
  );
};

export default SortableHeaderCellContent;
