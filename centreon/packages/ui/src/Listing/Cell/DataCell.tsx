import { makeStyles } from 'tss-react/mui';

import { Tooltip } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import DraggableIcon from '../Header/SortableCell/DraggableIcon';
import {
  Column,
  ColumnType,
  ComponentColumnProps,
  RowColorCondition
} from '../models';
import useStyleTable from '../useStyleTable';

import EllipsisTypography from './EllipsisTypography';

import Cell from '.';

interface Props {
  areColumnsEditable: boolean;
  column: Column;
  disableRowCondition: (row) => boolean;
  getHighlightRowCondition?: (row) => boolean;
  isRowHovered: boolean;
  isRowSelected: boolean;
  row?;
  rowColorConditions?: Array<RowColorCondition>;
  viewMode?: ListingVariant;
}

const useStyles = makeStyles()((theme) => ({
  cell: {
    alignItems: 'center',
    backgroundColor: 'transparent',
    display: 'flex',
    height: '100%',
    overflow: 'hidden',
    whiteSpace: 'nowrap'
  },
  componentColumn: {
    width: theme.spacing(2.75)
  },
  headerCell: {
    padding: theme.spacing(0, 0, 0, 1)
  },
  item: {
    paddingLeft: theme.spacing(1.5)
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
  disableRowCondition,
  viewMode,
  getHighlightRowCondition,
  areColumnsEditable
}: Props): JSX.Element | null => {
  const { dataStyle } = useStyleTable({ viewMode });
  const { classes } = useStyles();

  const commonCellProps = {
    align: 'left' as const,
    disableRowCondition,
    isRowHovered,
    row,
    rowColorConditions
  };

  const isRowHighlighted = getHighlightRowCondition?.(row);

  const cellByColumnType = {
    [ColumnType.string]: (): JSX.Element => {
      const { getFormattedString, isTruncated, getColSpan } = column;

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
          className={classes.item}
          isRowHighlighted={isRowHighlighted}
          style={{
            gridColumn
          }}
          viewMode={viewMode}
          {...commonCellProps}
        >
          {isTruncated && (
            <Tooltip title={formattedString}>{typography}</Tooltip>
          )}
          <>
            {areColumnsEditable && <DraggableIcon />}
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
          className={classes.item}
          isRowHighlighted={isRowHighlighted}
          viewMode={viewMode}
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
            {areColumnsEditable && (
              <DraggableIcon className={classes.componentColumn} />
            )}

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
          </>
        </Cell>
      );
    }
  };

  return cellByColumnType[column.type]();
};

export default DataCell;
