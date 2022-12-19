import { memo } from 'react';

import { useAtomValue } from 'jotai/utils';
import { equals, props } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Tooltip, Typography } from '@mui/material';

import { hoveredHeaderAtom } from '../Header/headerAtom';
import {
  Column,
  ColumnType,
  ComponentColumnProps,
  RowColorCondition
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

const useStyles = makeStyles()((theme) => ({
  cell: {
    alignItems: 'center',
    alignSelf: 'stretch',
    display: 'flex',
    overflow: 'hidden',
    whiteSpace: 'nowrap'
  },
  componentColumn: {
    padding: theme.spacing(0, 0, 0, 2.25)
  },
  headerCell: {
    padding: theme.spacing(0, 0, 0, 1)
  },

  hoveredComponentColumn: {
    padding: theme.spacing(0, 1.25, 0, 1)
  },
  hoveredStringColumn: {
    padding: theme.spacing(0, 1.5, 0, 1)
  },
  rowNotHovered: {
    color: theme.palette.text.secondary
  },
  stringColumn: {
    padding: theme.spacing(0, 0, 0, 2.5)
  },
  text: {
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  }
}));

const DataCell = ({
  row,
  column,
  isRowSelected,
  isRowHovered,
  rowColorConditions,
  disableRowCondition
}: Props): JSX.Element | null => {
  const { id, type } = column;

  const { classes, cx } = useStyles();

  const hoveredHeader = useAtomValue(hoveredHeaderAtom);

  const isHeaderOfCellHovered =
    equals(id, hoveredHeader?.column?.id) && hoveredHeader?.isHeaderHovered;

  const stringColumn =
    !isHeaderOfCellHovered && equals(type, ColumnType.string);

  const componentColumn =
    !isHeaderOfCellHovered && equals(type, ColumnType.component);

  const hoveredStringColumn =
    isHeaderOfCellHovered && equals(type, ColumnType.string);

  const hoveredComponentColumn =
    isHeaderOfCellHovered && equals(type, ColumnType.component);

  const commonCellProps = {
    align: 'left' as const,
    className: cx(classes.cell, {
      [classes.hoveredComponentColumn]: hoveredComponentColumn,
      [classes.hoveredStringColumn]: hoveredStringColumn,
      [classes.stringColumn]: stringColumn,
      [classes.componentColumn]: componentColumn
    }),
    compact: column.compact,
    disableRowCondition,
    isRowHovered,
    row,
    rowColorConditions
  };

  const cellByColumnType = {
    [ColumnType.string]: (): JSX.Element => {
      const { getFormattedString, isTruncated, getColSpan } = column;

      const colSpan = getColSpan?.(isRowSelected);

      const formattedString = getFormattedString?.(row) || '';

      const gridColumn = colSpan ? `auto / span ${colSpan}` : 'auto / auto';

      const typography = (
        <Typography
          className={cx(classes.text, {
            [classes.rowNotHovered]: !isRowHovered || disableRowCondition(row)
          })}
          variant="body2"
        >
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
        props: ComponentColumnProps
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
    }
  };

  return cellByColumnType[column.type]();
};

const MemoizedDataCell = memo<Props>(
  DataCell,
  (prevProps: Props, nextProps: Props): boolean => {
    const previousHasHoverableComponent =
      prevProps.column.hasHoverableComponent;
    const previousRenderComponentOnRowUpdate =
      prevProps.column.getRenderComponentOnRowUpdateCondition?.(prevProps.row);
    const previousRenderComponentCondition =
      prevProps.column.getRenderComponentCondition?.(prevProps.row);
    const previousRowMemoProps = prevProps.column.rowMemoProps;
    const previousIsComponentHovered =
      previousHasHoverableComponent && prevProps.isRowHovered;
    const previousFormattedString = prevProps.column.getFormattedString?.(
      prevProps.row
    );
    const previousIsTruncated = prevProps.column.isTruncated;
    const previousColSpan = prevProps.column.getColSpan?.(
      prevProps.isRowSelected
    );
    const previousHiddenCondition = prevProps.column.getHiddenCondition?.(
      prevProps.isRowSelected
    );

    const nextHasHoverableComponent = nextProps.column.hasHoverableComponent;
    const nextRenderComponentOnRowUpdate =
      nextProps.column.getRenderComponentOnRowUpdateCondition?.(nextProps.row);
    const nextRenderComponentCondition =
      nextProps.column.getRenderComponentCondition?.(nextProps.row);
    const nextRowMemoProps = nextProps.column.rowMemoProps;
    const nextIsComponentHovered =
      nextHasHoverableComponent && nextProps.isRowHovered;

    const nextFormattedString = nextProps.column.getFormattedString?.(
      nextProps.row
    );

    const nextColSpan = nextProps.column.getColSpan?.(nextProps.isRowSelected);

    const nextHiddenCondition = nextProps.column.getHiddenCondition?.(
      nextProps.isRowSelected
    );
    const nextIsTruncated = nextProps.column.isTruncated;

    const prevRowColors = prevProps.rowColorConditions?.map(({ condition }) =>
      condition(prevProps.row)
    );
    const nextRowColors = nextProps.rowColorConditions?.map(({ condition }) =>
      condition(nextProps.row)
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
      ? props(previousRowMemoProps, prevProps.row)
      : prevProps.row;
    const nextRowProps = nextRowMemoProps
      ? props(nextRowMemoProps, nextProps.row)
      : nextProps.row;

    return (
      equals(previousIsComponentHovered, nextIsComponentHovered) &&
      equals(prevProps.isRowHovered, nextProps.isRowHovered) &&
      equals(previousFormattedString, nextFormattedString) &&
      equals(previousColSpan, nextColSpan) &&
      equals(previousIsTruncated, nextIsTruncated) &&
      equals(previousHiddenCondition, nextHiddenCondition) &&
      equals(
        previousRenderComponentOnRowUpdate && previousRowProps,
        nextRenderComponentOnRowUpdate && nextRowProps
      ) &&
      equals(
        previousFormattedString ?? previousRowProps,
        nextFormattedString ?? nextRowProps
      ) &&
      equals(prevRowColors, nextRowColors) &&
      equals(
        prevProps.disableRowCondition(prevProps.row),
        nextProps.disableRowCondition(nextProps.row)
      )
    );
  }
);

export default MemoizedDataCell;
export { useStyles, Props };
