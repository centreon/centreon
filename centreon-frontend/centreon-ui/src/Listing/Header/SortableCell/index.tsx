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
  transition?: string;
  transform: Transform | null;
  isSorting: boolean;
}

const useStyles = makeStyles<Theme, StylesProps>(() => ({
  item: ({ transform, transition, isSorting }: StylesProps) => {
    return {
      opacity: isSorting ? 0.5 : 1,
      transition: transition || undefined,
      transform: CSS.Translate.toString(transform),
      display: 'flex',
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
    transition: transition || undefined,
    isSorting,
    transform,
  });
  const cellClasses = useCellStyles({ listingCheckable: true });

  return (
    <HeaderCell
      key={column.id}
      padding={column.compact ? 'none' : 'default'}
      sortDirection={equals(sortField, column.id) ? sortOrder : false}
      component="div"
      className={clsx([cellClasses.cell, classes.item])}
    >
      <SortableHeaderCellContent
        columnConfiguration={columnConfiguration}
        column={column}
        sortField={sortField}
        sortOrder={sortOrder}
        onSort={onSort}
        ref={sortableRef}
        {...attributes}
        {...listeners}
      />
    </HeaderCell>
  );
};

export default SortableHeaderCell;
