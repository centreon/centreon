import * as React from 'react';

import { equals } from 'ramda';

import { makeStyles, Tooltip, Typography, Theme } from '@material-ui/core';

import {
  Column,
  ColumnType,
  ComponentColumnProps,
  RowColorCondition,
} from '../models';

import Cell from '.';

interface Props {
  column: Column;
  isRowHovered: boolean;
  isRowSelected: boolean;
  listingCheckable: boolean;
  row?;
  rowColorConditions?: Array<RowColorCondition>;
}

const useStyles = makeStyles<Theme, { listingCheckable: boolean }>(() => ({
  cell: {
    alignItems: 'center',
    alignSelf: 'stretch',
    display: 'flex',
    overflow: 'hidden',
    whiteSpace: 'nowrap',
  },
  text: {
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
}));

const DataCell = ({
  row,
  column,
  listingCheckable,
  isRowSelected,
  isRowHovered,
  rowColorConditions,
}: Props): JSX.Element | null => {
  const classes = useStyles({ listingCheckable });

  const commonCellProps = {
    align: 'left' as const,
    className: classes.cell,
    compact: column.compact,
    isRowHovered,
    row,
    rowColorConditions,
  };

  const cellByColumnType = {
    [ColumnType.string]: (): JSX.Element => {
      const { getFormattedString, isTruncated, getColSpan } = column;

      const colSpan = getColSpan?.(isRowSelected);

      const formattedString = getFormattedString?.(row) || '';

      const gridColumn = colSpan ? `auto / span ${colSpan}` : 'auto / auto';

      const typography = (
        <Typography className={classes.text} variant="body2">
          {formattedString}
        </Typography>
      );

      return (
        <Cell style={{ gridColumn }} {...commonCellProps}>
          {isTruncated && (
            <Tooltip title={formattedString}>{typography}</Tooltip>
          )}
          {!isTruncated && typography}
        </Cell>
      );
    },
    [ColumnType.component]: (): JSX.Element | null => {
      const { getHiddenCondition, clickable } = column;
      const Component = column.Component as (
        props: ComponentColumnProps,
      ) => JSX.Element;

      const isCellHidden = getHiddenCondition?.(isRowSelected);

      if (isCellHidden) {
        return null;
      }

      return (
        <Cell
          onClick={(e): void => {
            if (!clickable) {
              return;
            }
            e.preventDefault();
            e.stopPropagation();
          }}
          {...commonCellProps}
        >
          <Component
            isHovered={isRowHovered}
            isSelected={isRowSelected}
            row={row}
          />
        </Cell>
      );
    },
  };

  return cellByColumnType[column.type]();
};

const MemoizedDataCell = React.memo<Props>(
  DataCell,
  (prevProps, nextProps): boolean => {
    const {
      column: previousColumn,
      row: previousRow,
      isRowHovered: previousIsRowHovered,
      isRowSelected: previousIsRowSelected,
      rowColorConditions: previousRowColorConditions,
    } = prevProps;
    const previousHasHoverableComponent = previousColumn.hasHoverableComponent;
    const previousRenderComponentOnRowUpdate = previousColumn.getRenderComponentOnRowUpdateCondition?.(
      previousRow,
    );
    const previousRenderComponentCondition = previousColumn.getRenderComponentCondition?.(
      previousRow,
    );
    const previousIsComponentHovered =
      previousHasHoverableComponent && previousIsRowHovered;
    const previousFormattedString = previousColumn.getFormattedString?.(
      previousRow,
    );
    const previousIsTruncated = previousColumn.isTruncated;
    const previousColSpan = previousColumn.getColSpan?.(previousIsRowSelected);
    const previousHiddenCondition = previousColumn.getHiddenCondition?.(
      previousIsRowSelected,
    );

    const {
      column: nextColumn,
      row: nextRow,
      isRowHovered: nextIsRowHovered,
      isRowSelected: nextIsRowSelected,
      rowColorConditions: nextRowColorConditions,
    } = nextProps;
    const nextHasHoverableComponent = nextColumn.hasHoverableComponent;
    const nextRenderComponentOnRowUpdate = nextColumn.getRenderComponentOnRowUpdateCondition?.(
      nextRow,
    );
    const nextRenderComponentCondition = nextColumn.getRenderComponentCondition?.(
      nextRow,
    );
    const nextIsComponentHovered =
      nextHasHoverableComponent && nextIsRowHovered;

    const nextFormatttedString = nextColumn.getFormattedString?.(nextRow);

    const nextColSpan = nextColumn.getColSpan?.(nextIsRowSelected);

    const nextHiddenCondition = nextColumn.getHiddenCondition?.(
      nextIsRowSelected,
    );
    const nextIsTruncated = nextColumn.isTruncated;

    const prevRowColors = previousRowColorConditions?.map(({ condition }) =>
      condition(previousRow),
    );
    const nextRowColors = nextRowColorConditions?.map(({ condition }) =>
      condition(nextRow),
    );

    // Explicitely render the Component.
    if (previousRenderComponentCondition && nextRenderComponentCondition) {
      return false;
    }

    // Explicitely prevent the component from rendering.
    if (nextRenderComponentCondition === false) {
      return true;
    }

    return (
      equals(previousIsComponentHovered, nextIsComponentHovered) &&
      equals(previousIsRowHovered, nextIsRowHovered) &&
      equals(previousFormattedString, nextFormatttedString) &&
      equals(previousColSpan, nextColSpan) &&
      equals(previousIsTruncated, nextIsTruncated) &&
      equals(previousHiddenCondition, nextHiddenCondition) &&
      equals(
        previousRenderComponentOnRowUpdate && previousRow,
        nextRenderComponentOnRowUpdate && nextRow,
      ) &&
      equals(previousRow, nextRow) &&
      equals(prevRowColors, nextRowColors)
    );
  },
);

export default MemoizedDataCell;
export { useStyles, Props };
