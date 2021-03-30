/* eslint-disable react/no-unused-prop-types */

import * as React from 'react';

import { equals } from 'ramda';

import { TableRowProps, TableRow, makeStyles, Theme } from '@material-ui/core';

import { ColumnConfiguration, RowColorCondition } from './models';

const useStyles = makeStyles<Theme>(() => {
  return {
    row: {
      display: 'contents',
      width: '100%',
      cursor: 'pointer',
    },
  };
});

type Props = {
  children;
  isHovered?: boolean;
  isSelected?: boolean;
  row;
  rowColorConditions: Array<RowColorCondition>;
  columnIds: Array<string>;
  columnConfiguration?: ColumnConfiguration;
} & TableRowProps;

const Row = React.memo<Props>(
  ({
    children,
    tabIndex,
    onMouseOver,
    onFocus,
    onClick,
  }: Props & TableRowProps): JSX.Element => {
    const classes = useStyles();

    return (
      <TableRow
        tabIndex={tabIndex}
        onMouseOver={onMouseOver}
        className={classes.row}
        onFocus={onFocus}
        onClick={onClick}
        component="div"
      >
        {children}
      </TableRow>
    );
  },
  (prevProps, nextProps) => {
    const {
      row: previousRow,
      rowColorConditions: previousRowColorConditions,
    } = prevProps;
    const {
      row: nextRow,
      rowColorConditions: nextRowColorConditions,
    } = nextProps;

    const previousRowColors = previousRowColorConditions?.map(({ condition }) =>
      condition(previousRow),
    );
    const nextRowColors = nextRowColorConditions?.map(({ condition }) =>
      condition(nextRow),
    );

    return (
      equals(prevProps.isHovered, nextProps.isHovered) &&
      equals(prevProps.isSelected, nextProps.isSelected) &&
      equals(prevProps.row, nextProps.row) &&
      equals(prevProps.className, nextProps.className) &&
      equals(previousRowColors, nextRowColors) &&
      equals(prevProps.columnIds, nextProps.columnIds) &&
      equals(prevProps.columnConfiguration, nextProps.columnConfiguration)
    );
  },
);

export default Row;
