import { memo } from 'react';

import { equals, props } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Tooltip } from '@mui/material';

import DraggableIcon from '../Header/SortableCell/DraggableIconIcon';
import {
  Column,
  ColumnType,
  ComponentColumnProps,
  RowColorCondition
} from '../models';

import EllipsisTypography from './EllipsisTypography';

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
    padding: 0,
    whiteSpace: 'nowrap'
  },
  componentColumn: {
    width: theme.spacing(2.25)
  },
  headerCell: {
    padding: theme.spacing(0, 0, 0, 1)
  },
  rowNotHovered: {
    color: theme.palette.text.secondary
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
  const { classes, cx } = useStyles();

  const commonCellProps = {
    align: 'left' as const,
    className: cx(classes.cell),
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
        <EllipsisTypography
          disableRowCondition={disableRowCondition(row)}
          formattedString={formattedString}
          isRowHovered={isRowHovered}
        />
      );

      return (
        <Cell style={{ gridColumn }} {...commonCellProps}>
          {isTruncated && (
            <Tooltip title={formattedString}>{typography}</Tooltip>
          )}
          <>
            <DraggableIcon />
            {!isTruncated && typography}
          </>
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
          <>
            <DraggableIcon className={classes.componentColumn} />

            <Component
              isHovered={isRowHovered}
              isSelected={isRowSelected}
              renderEllipsisTypography={({
                className,
                formattedString
              }): JSX.Element => {
                return (
                  <EllipsisTypography
                    className={className}
                    disableRowCondition={disableRowCondition(row)}
                    formattedString={formattedString}
                    isRowHovered={isRowHovered}
                  />
                );
              }}
              row={row}
            />
          </>
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
