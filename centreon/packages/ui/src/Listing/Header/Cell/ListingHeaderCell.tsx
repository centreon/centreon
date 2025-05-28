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

import useStyleTable from '../../useStyleTable';
import { StylesProps } from './ListingHeaderCell.styles';

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
  const { dataStyle } = useStyleTable({ listingVariant });
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
      <span>
        <HeaderLabel>{columnLabel}</HeaderLabel>
      </span>
    </Tooltip>
  );

  return (
    <TableCell
      className={`bg-background-listing-header border-b-0 h-[inherit] py-0 px-2 ${isInDragOverlay && 'block opacity-70'}`}
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
      ref={itemRef}
      style={{
        ...style,
        height: dataStyle.header.height
      }}
    >
      <div className="flex items-center h-full justify-between text-white p-0">
        {column.sortable ? (
          <TableSortLabel
            active={sortField === columnSortField}
            aria-label={`Column ${column.label}`}
            className="text-white"
            classes={{
              icon: 'text-white'
            }}
            direction={sortOrder || 'desc'}
            onClick={sort}
          >
            {headerContent}
          </TableSortLabel>
        ) : (
          <div className="mr-4 inline-flex items-center select-none">
            {headerContent}
          </div>
        )}

        {columnConfiguration?.sortable && areColumnsEditable && (
          <DraggableIconButton
            {...props}
            className={`p-0 ${isDragging ? 'cursor-grabbing' : 'cursor-grab'} text-white  opacity-0 hover:opacity-100 focus:opacity-100`}
            columnLabel={columnLabel}
          />
        )}
      </div>
    </TableCell>
  );
};

export default ListingHeaderCell;
