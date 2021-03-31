import * as React from 'react';

import { useSortable } from '@dnd-kit/sortable';
import { CSS, Transform } from '@dnd-kit/utilities';
import clsx from 'clsx';
import { equals } from 'ramda';

import { makeStyles, Theme } from '@material-ui/core';

import { Column } from '../../models';
import { useStyles as useCellStyles } from '../../Cell/DataCell';
import { ListingProps } from '../../..';
import { HeaderCell } from '..';

import SortableHeaderCellContent from './Content';

interface StylesProps {
  isSorting: boolean;
  transform: Transform | null;
  transition?: string;
}

const useStyles = makeStyles<Theme, StylesProps>(() => ({
  item: ({ transform, transition, isSorting }: StylesProps) => {
    return {
      display: 'flex',
      opacity: isSorting ? 0.5 : 1,
      transform: CSS.Translate.toString(transform),
      transition: transition || undefined,
    };
  },
}));

type Props = Pick<
  ListingProps<unknown>,
  | 'onSort'
  | 'sortOrder'
  | 'sortField'
  | 'columnConfiguration'
  | 'onSelectColumns'
> & { column: Column };

const SortableHeaderCell = ({
  column,
  columnConfiguration,
  onSort,
  sortOrder,
  sortField,
}: Props): JSX.Element => {
  const { id } = column;

  const {
    attributes,
    listeners,
    setNodeRef: sortableRef,
    transition,
    transform,
    isSorting,
  } = useSortable({ id });

  const classes = useStyles({
    isSorting,
    transform,
    transition: transition || undefined,
  });
  const cellClasses = useCellStyles({ listingCheckable: true });

  return (
    <HeaderCell
      className={clsx([cellClasses.cell, classes.item])}
      component="div"
      key={column.id}
      padding={column.compact ? 'none' : 'default'}
      sortDirection={equals(sortField, column.id) ? sortOrder : false}
    >
      <SortableHeaderCellContent
        column={column}
        columnConfiguration={columnConfiguration}
        ref={sortableRef}
        sortField={sortField}
        sortOrder={sortOrder}
        onSort={onSort}
        {...attributes}
        {...listeners}
      />
    </HeaderCell>
  );
};

export default SortableHeaderCell;
