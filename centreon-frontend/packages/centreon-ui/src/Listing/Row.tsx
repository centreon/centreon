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
  disableRowCondition: (row) => boolean;
  isHovered?: boolean;
  isSelected?: boolean;
  isShiftKeyDown: boolean;
  lastSelectionIndex: number | null;
  row;
  rowColorConditions: Array<RowColorCondition>;
  shiftKeyDownRowPivot: number | null;
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
      isShiftKeyDown: prevIsShiftKeyDown,
      shiftKeyDownRowPivot: prevShiftKeyDownRowPivot,
      lastSelectionIndex: prevLastSelectionIndex,
    } = prevProps;
    const {
      row: nextRow,
      rowColorConditions: nextRowColorConditions,
      isInViewport: nextIsInViewport,
      visibleColumns: nextVisibleColumns,
      isShiftKeyDown: nextIsShiftKeyDown,
      shiftKeyDownRowPivot: nextShiftKeyDownRowPivot,
      lastSelectionIndex: nextLastSelectionIndex,
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

    if (not(equals(prevProps.isHovered, nextProps.isHovered))) {
      return false;
    }

    if (not(nextIsInViewport)) {
      return equals(prevProps.isSelected, nextProps.isSelected);
    }

    const previousRowColors = previousRowColorConditions?.map(({ condition }) =>
      condition(previousRow),
    );
    const nextRowColors = nextRowColorConditions?.map(({ condition }) =>
      condition(nextRow),
    );

    return (
      equals(prevProps.isSelected, nextProps.isSelected) &&
      equals(prevProps.row, nextProps.row) &&
      equals(prevProps.className, nextProps.className) &&
      equals(previousRowColors, nextRowColors) &&
      equals(prevProps.columnIds, nextProps.columnIds) &&
      equals(prevProps.columnConfiguration, nextProps.columnConfiguration) &&
      equals(prevIsShiftKeyDown, nextIsShiftKeyDown) &&
      equals(prevShiftKeyDownRowPivot, nextShiftKeyDownRowPivot) &&
      equals(prevLastSelectionIndex, nextLastSelectionIndex)
    );
  },
);

const IntersectionRow = (props: Props): JSX.Element => {
  const rowRef = React.useRef<HTMLDivElement | null>(null);
  const { isInViewport, setElement } = useViewportIntersection();
  const classes = useStyles();

  const getFirstCellElement = (): ChildNode | null | undefined =>
    rowRef.current?.firstChild?.firstChild?.firstChild;

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
