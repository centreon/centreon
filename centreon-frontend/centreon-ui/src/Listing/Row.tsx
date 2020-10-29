/* eslint-disable react/no-unused-prop-types */

import * as React from 'react';

import { equals } from 'ramda';
import clsx from 'clsx';

import {
  TableRowProps,
  TableRow,
  makeStyles,
  Theme,
  fade,
} from '@material-ui/core';

import { RowColorCondition } from './models';

const useStyles = (rowColorConditions): (() => Record<string, string>) =>
  makeStyles<Theme>((theme) => {
    const rowColorClasses = rowColorConditions.reduce(
      (rowColorConditionClasses, { name, color }) => ({
        ...rowColorConditionClasses,
        [name]: {
          backgroundColor: color,
        },
      }),
      {},
    );

    return {
      row: {
        cursor: 'pointer',
        '&:hover': {
          backgroundColor: fade(theme.palette.primary.main, 0.08),
        },
      },
      ...rowColorClasses,
    };
  });

type Props = {
  children;
  isHovered?: boolean;
  isSelected?: boolean;
  row;
  rowColorConditions;
} & TableRowProps;

const getRowColor = ({ conditions, row }): RowColorCondition =>
  conditions.find(({ condition }) => condition(row));

const Row = React.memo<Props>(
  ({
    children,
    tabIndex,
    onMouseOver,
    onFocus,
    onClick,
    row,
    rowColorConditions,
  }: Props & TableRowProps): JSX.Element => {
    const classes = useStyles(rowColorConditions)();

    const rowColor = getRowColor({ conditions: rowColorConditions, row });

    return (
      <TableRow
        tabIndex={tabIndex}
        onMouseOver={onMouseOver}
        className={clsx([classes.row, classes[rowColor?.name]])}
        onFocus={onFocus}
        onClick={onClick}
      >
        {children}
      </TableRow>
    );
  },
  (prevProps, nextProps) => {
    return (
      equals(prevProps.isHovered, nextProps.isHovered) &&
      equals(prevProps.isSelected, nextProps.isSelected) &&
      equals(prevProps.row, nextProps.row) &&
      equals(prevProps.className, nextProps.className) &&
      equals(
        getRowColor({
          conditions: prevProps.rowColorConditions,
          row: prevProps.row,
        }),
        getRowColor({
          conditions: nextProps.rowColorConditions,
          row: nextProps.row,
        }),
      )
    );
  },
);

export default Row;
