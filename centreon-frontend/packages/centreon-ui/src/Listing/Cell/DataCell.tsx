import * as React from 'react';

import { equals, props } from 'ramda';

import { makeStyles, Tooltip, Typography } from '@material-ui/core';

import {
  Column,
  ColumnType,
  ComponentColumnProps,
  RowColorCondition,
} from '../models';

import Cell from '.';

interface Props {
  column: Column;
  disableRowCondition: (row) => boolean;
  isRowHovered: boolean;
  isRowSelected: boolean;
  row?;
  rowColorConditions?: Array<RowColorCondition>;
}

const useStyles = makeStyles(() => ({
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
  isRowSelected,
  isRowHovered,
  rowColorConditions,
  disableRowCondition,
}: Props): JSX.Element | null => {
  const classes = useStyles();

  const commonCellProps = {
    align: 'left' as const,
    className: classes.cell,
    compact: column.compact,
    disableRowCondition,
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
      disableRowCondition: prevDisableRowCondition,
    } = prevProps;
    const previousHasHoverableComponent = previousColumn.hasHoverableComponent;
    const previousRenderComponentOnRowUpdate =
      previousColumn.getRenderComponentOnRowUpdateCondition?.(previousRow);
    const previousRenderComponentCondition =
      previousColumn.getRenderComponentCondition?.(previousRow);
    const previousRowMemoProps = previousColumn.rowMemoProps;
    const previousIsComponentHovered =
      previousHasHoverableComponent && previousIsRowHovered;
    const previousFormattedString =
      previousColumn.getFormattedString?.(previousRow);
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
      disableRowCondition: nextDisableRowCondition,
    } = nextProps;
    const nextHasHoverableComponent = nextColumn.hasHoverableComponent;
    const nextRenderComponentOnRowUpdate =
      nextColumn.getRenderComponentOnRowUpdateCondition?.(nextRow);
    const nextRenderComponentCondition =
      nextColumn.getRenderComponentCondition?.(nextRow);
    const nextRowMemoProps = nextColumn.rowMemoProps;
    const nextIsComponentHovered =
      nextHasHoverableComponent && nextIsRowHovered;

    const nextFormattedString = nextColumn.getFormattedString?.(nextRow);

    const nextColSpan = nextColumn.getColSpan?.(nextIsRowSelected);

    const nextHiddenCondition =
      nextColumn.getHiddenCondition?.(nextIsRowSelected);
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

    const previousRowProps = previousRowMemoProps
      ? props(previousRowMemoProps, previousRow)
      : previousRow;
    const nextRowProps = nextRowMemoProps
      ? props(nextRowMemoProps, nextRow)
      : nextRow;

    return (
      equals(previousIsComponentHovered, nextIsComponentHovered) &&
      equals(previousIsRowHovered, nextIsRowHovered) &&
      equals(previousFormattedString, nextFormattedString) &&
      equals(previousColSpan, nextColSpan) &&
      equals(previousIsTruncated, nextIsTruncated) &&
      equals(previousHiddenCondition, nextHiddenCondition) &&
      equals(
        previousRenderComponentOnRowUpdate && previousRowProps,
        nextRenderComponentOnRowUpdate && nextRowProps,
      ) &&
      equals(
        previousFormattedString ?? previousRowProps,
        nextFormattedString ?? nextRowProps,
      ) &&
      equals(prevRowColors, nextRowColors) &&
      equals(
        prevDisableRowCondition(previousRow),
        nextDisableRowCondition(nextRow),
      )
    );
  },
);

export default MemoizedDataCell;
export { useStyles, Props };
