/* eslint-disable react/no-unused-prop-types */

import * as React from 'react';

import { equals, not, pluck } from 'ramda';

import { TableRowProps, TableRow, makeStyles, Theme } from '@material-ui/core';

import { useViewportIntersection } from '../utils/useViewportIntersection';

import { Column, ColumnConfiguration, RowColorCondition } from './models';

const useStyles = makeStyles<Theme>((theme) => {
  return {
    intersectionRow: {
      display: 'contents',
      width: '100%',
    },
    row: {
      cursor: 'pointer',
      display: 'contents',
      width: '100%',
    },
    skeleton: {
      height: theme.spacing(2.5),
      margin: theme.spacing(0.5, 0),
      width: '100%',
    },
  };
});

type Props = {
  children;
  columnConfiguration?: ColumnConfiguration;
  columnIds: Array<string>;
  isHovered?: boolean;
  isSelected?: boolean;
  row;
  rowColorConditions: Array<RowColorCondition>;
  visibleColumns: Array<Column>;
} & TableRowProps;

type RowProps = {
  isInViewport: boolean;
} & Props;

const Row = React.memo<RowProps>(
  ({
    children,
    tabIndex,
    onMouseOver,
    onFocus,
    onClick,
  }: RowProps): JSX.Element => {
    const classes = useStyles();

    return (
      <TableRow
        className={classes.row}
        component="div"
        tabIndex={tabIndex}
        onClick={onClick}
        onFocus={onFocus}
        onMouseOver={onMouseOver}
      >
        {children}
      </TableRow>
    );
  },
  (prevProps, nextProps) => {
    const {
      row: previousRow,
      rowColorConditions: previousRowColorConditions,
      visibleColumns: previousVisibleColumns,
    } = prevProps;
    const {
      row: nextRow,
      rowColorConditions: nextRowColorConditions,
      isInViewport: nextIsInViewport,
      visibleColumns: nextVisibleColumns,
    } = nextProps;

    if (
      not(
        equals(
          pluck('id', previousVisibleColumns),
          pluck('id', nextVisibleColumns),
        ),
      )
    ) {
      return false;
    }

    if (not(nextIsInViewport)) {
      return true;
    }

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

const IntersectionRow = (props: Props): JSX.Element => {
  const rowRef = React.useRef<HTMLDivElement | null>(null);
  const { isInViewport, setElement } = useViewportIntersection();
  const classes = useStyles();

  const getFirstCellElement = () => rowRef.current?.firstChild?.firstChild;

  React.useEffect(() => {
    setElement(getFirstCellElement() as HTMLDivElement);
  }, [getFirstCellElement()]);

  return (
    <div className={classes.intersectionRow} ref={rowRef}>
      <Row {...props} isInViewport={isInViewport} />
    </div>
  );
};

export default IntersectionRow;
