import { memo } from 'react';

import { equals, props } from 'ramda';

import { Tooltip } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import {
  Column,
  ColumnType,
  ComponentColumnProps,
  RowColorCondition
} from '../models';
import useStyleTable from '../useStyleTable';

import { useStyles } from './DataCell.styles';
import EllipsisTypography from './EllipsisTypography';

import Cell from '.';

interface Props {
  column: Column;
  disableRowCondition: (row) => boolean;
  getHighlightRowCondition?: (row) => boolean;
  isRowHovered: boolean;
  isRowSelected: boolean;
  labelCollapse?: string;
  labelExpand?: string;
  listingVariant?: ListingVariant;
  row?;
  rowColorConditions?: Array<RowColorCondition>;
  subItemsRowProperty?: string;
}

const DataCell = ({
  row,
  column,
  isRowSelected,
  isRowHovered,
  rowColorConditions,
  disableRowCondition,
  listingVariant,
  getHighlightRowCondition,
  subItemsRowProperty,
  labelCollapse,
  labelExpand
}: Props): JSX.Element | null => {
  const { classes, cx } = useStyles();
  const { dataStyle } = useStyleTable({ listingVariant });

  const commonCellProps = {
    disableRowCondition,
    displaySubItemsCaret: column.displaySubItemsCaret,
    isRowHovered,
    labelCollapse,
    labelExpand,
    row,
    rowColorConditions,
    subItemsRowProperty
  };

  const isRowHighlighted = getHighlightRowCondition?.(row);

  const cellByColumnType = {
    [ColumnType.string]: (): JSX.Element => {
      const { getFormattedString, isTruncated, getColSpan, align } = column;

      const colSpan = getColSpan?.(isRowSelected);

      const formattedString = getFormattedString?.(row) || '';

      const gridColumn = colSpan ? `auto / span ${colSpan}` : 'auto / auto';

      const typography = (
        <EllipsisTypography
          dataStyle={dataStyle}
          disableRowCondition={disableRowCondition(row)}
          formattedString={formattedString}
          isRowHovered={isRowHovered}
        />
      );

      return (
        <Cell
          className={classes.cell}
          isRowHighlighted={isRowHighlighted}
          listingVariant={listingVariant}
          style={{
            alignItems: align,
            gridColumn
          }}
          {...commonCellProps}
        >
          {isTruncated && (
            <Tooltip title={formattedString}>{typography}</Tooltip>
          )}
          {!isTruncated && typography}
        </Cell>
      );
    },
    [ColumnType.component]: (): JSX.Element | null => {
      const { getHiddenCondition, clickable, align } = column;
      const Component = column.Component as (
        props: ComponentColumnProps
      ) => JSX.Element;

      const isCellHidden = getHiddenCondition?.(isRowSelected);

      if (isCellHidden) {
        return (
          <Cell
            className={classes.cell}
            isRowHighlighted={isRowHighlighted}
            listingVariant={listingVariant}
            onClick={(e): void => {
              if (!clickable) {
                return;
              }
              e.preventDefault();
              e.stopPropagation();
            }}
            {...commonCellProps}
          />
        );
      }

      return (
        <Cell
          className={cx(classes.cell, clickable && classes.clickable)}
          isRowHighlighted={isRowHighlighted}
          listingVariant={listingVariant}
          style={{
            alignItems: align
          }}
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
            renderEllipsisTypography={({
              className,
              formattedString
            }): JSX.Element => {
              return (
                <EllipsisTypography
                  className={className}
                  dataStyle={dataStyle}
                  disableRowCondition={disableRowCondition(row)}
                  formattedString={formattedString}
                  isRowHovered={isRowHovered}
                />
              );
            }}
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
    const previousIsRowHighlighted = prevProps.getHighlightRowCondition?.(
      prevProps.row
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

    const previousRowConditions = prevProps.rowColorConditions?.map(
      ({ condition }) => condition(prevProps.row)
    );
    const nextRowConditions = nextProps.rowColorConditions?.map(
      ({ condition }) => condition(nextProps.row)
    );

    const previousRowColors = prevProps.rowColorConditions?.map(
      ({ color }) => color
    );
    const nextRowColors = nextProps.rowColorConditions?.map(
      ({ color }) => color
    );

    const nextIsRowHighlighted = nextProps.getHighlightRowCondition?.(
      nextProps.row
    );

    // Explicitely render the Component.
    if (previousRenderComponentCondition && nextRenderComponentCondition) {
      return false;
    }

    // Explicitely prevent the component from rendering.
    if (nextRenderComponentCondition === false) {
      return equals(prevProps.listingVariant, nextProps.listingVariant);
    }

    const previousRowProps = previousRowMemoProps
      ? props(previousRowMemoProps, prevProps.row)
      : prevProps.row;
    const nextRowProps = nextRowMemoProps
      ? props(nextRowMemoProps, nextProps.row)
      : nextProps.row;

    return (
      equals(prevProps.row, nextProps.row) &&
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
      equals(previousRowConditions, nextRowConditions) &&
      equals(previousRowColors, nextRowColors) &&
      equals(
        prevProps.disableRowCondition(prevProps.row),
        nextProps.disableRowCondition(nextProps.row)
      ) &&
      equals(previousIsRowHighlighted, nextIsRowHighlighted) &&
      equals(prevProps.listingVariant, nextProps.listingVariant) &&
      equals(prevProps.subItemsRowProperty, nextProps.subItemsRowProperty)
    );
  }
);

export default MemoizedDataCell;
export { useStyles, type Props };
