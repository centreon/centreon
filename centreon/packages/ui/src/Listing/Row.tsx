/* eslint-disable react/no-unused-prop-types */

import { memo, useRef, useEffect } from 'react';

import { equals, gte, lt, not, pluck } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { TableRowProps, TableRow, useTheme } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { useViewportIntersection } from '../utils/useViewportIntersection';
import LoadingSkeleton from '../LoadingSkeleton';

import { Column, ColumnConfiguration, RowColorCondition } from './models';

import { performanceRowsLimit } from '.';

const useStyles = makeStyles()((theme) => {
  return {
    intersectionRow: {
      display: 'contents',
      width: '100%'
    },
    row: {
      cursor: 'pointer',
      display: 'contents',
      width: '100%'
    },
    skeleton: {
      height: theme.spacing(2.5),
      width: '100%'
    },
    skeletonContainer: {
      padding: theme.spacing(0.5)
    }
  };
});

type Props = {
  checkable: boolean;
  children;
  columnConfiguration?: ColumnConfiguration;
  columnIds: Array<string>;
  disableRowCondition: (row) => boolean;
  isHovered?: boolean;
  isSelected?: boolean;
  isShiftKeyDown: boolean;
  lastSelectionIndex: number | null;
  limit: number;
  row;
  rowColorConditions: Array<RowColorCondition>;
  shiftKeyDownRowPivot: number | null;
  viewMode?: ListingVariant;
  visibleColumns: Array<Column>;
} & TableRowProps;

type RowProps = {
  isInViewport: boolean;
} & Props;

const Row = memo<RowProps>(
  ({
    children,
    tabIndex,
    onMouseOver,
    onFocus,
    onClick,
    isInViewport,
    visibleColumns,
    checkable,
    limit
  }: RowProps): JSX.Element => {
    const { classes } = useStyles();

    if (not(isInViewport) && gte(limit, performanceRowsLimit)) {
      return (
        <div style={{ display: 'contents' }}>
          {checkable && (
            <div className={classes.skeletonContainer}>
              <div>
                <LoadingSkeleton className={classes.skeleton} />
              </div>
            </div>
          )}
          {visibleColumns.map(({ id }) => (
            <div className={classes.skeletonContainer} key={`loading_${id}`}>
              <div>
                <LoadingSkeleton className={classes.skeleton} />
              </div>
            </div>
          ))}
        </div>
      );
    }

    return (
      <TableRow
        className={classes.row}
        component="div"
        role={undefined}
        tabIndex={tabIndex}
        onClick={onClick}
        onFocus={onFocus}
        onMouseOver={onMouseOver}
      >
        {children}
      </TableRow>
    );
  },
  (prevProps: RowProps, nextProps: RowProps) => {
    const {
      row: previousRow,
      rowColorConditions: previousRowColorConditions,
      isInViewport: prevIsInViewport,
      visibleColumns: previousVisibleColumns,
      isShiftKeyDown: prevIsShiftKeyDown,
      shiftKeyDownRowPivot: prevShiftKeyDownRowPivot,
      lastSelectionIndex: prevLastSelectionIndex,
      viewMode: prevViewMode
    } = prevProps;
    const {
      row: nextRow,
      rowColorConditions: nextRowColorConditions,
      isInViewport: nextIsInViewport,
      visibleColumns: nextVisibleColumns,
      isShiftKeyDown: nextIsShiftKeyDown,
      shiftKeyDownRowPivot: nextShiftKeyDownRowPivot,
      lastSelectionIndex: nextLastSelectionIndex,
      limit: nextLimit,
      viewMode: nextViewMode
    } = nextProps;

    if (
      not(
        equals(
          pluck('id', previousVisibleColumns),
          pluck('id', nextVisibleColumns)
        )
      )
    ) {
      return false;
    }

    if (not(equals(prevProps.isHovered, nextProps.isHovered))) {
      return false;
    }

    const isNoLongerInViewport = not(prevIsInViewport) && not(nextIsInViewport);

    if (isNoLongerInViewport && gte(nextLimit, performanceRowsLimit)) {
      return true;
    }

    const isBackInViewport = not(prevIsInViewport) && nextIsInViewport;

    if (isBackInViewport && gte(nextLimit, performanceRowsLimit)) {
      return false;
    }

    const previousRowColors = previousRowColorConditions?.map(({ condition }) =>
      condition(previousRow)
    );
    const nextRowColors = nextRowColorConditions?.map(({ condition }) =>
      condition(nextRow)
    );

    if (not(nextIsInViewport) && lt(nextLimit, 60)) {
      return (
        equals(prevProps.isSelected, nextProps.isSelected) &&
        equals(prevProps.row, nextProps.row) &&
        equals(previousRowColors, nextRowColors) &&
        equals(prevProps.className, nextProps.className)
      );
    }

    return (
      equals(prevProps.isSelected, nextProps.isSelected) &&
      equals(prevProps.row, nextProps.row) &&
      equals(prevProps.className, nextProps.className) &&
      equals(previousRowColors, nextRowColors) &&
      equals(prevProps.columnIds, nextProps.columnIds) &&
      equals(prevProps.columnConfiguration, nextProps.columnConfiguration) &&
      equals(prevIsShiftKeyDown, nextIsShiftKeyDown) &&
      equals(prevShiftKeyDownRowPivot, nextShiftKeyDownRowPivot) &&
      equals(prevLastSelectionIndex, nextLastSelectionIndex) &&
      equals(prevViewMode, nextViewMode)
    );
  }
);

const IntersectionRow = (props: Props): JSX.Element => {
  const rowRef = useRef<HTMLDivElement | null>(null);
  const theme = useTheme();
  const { isInViewport, setElement } = useViewportIntersection({
    root: rowRef.current?.parentElement?.parentElement?.parentElement,
    rootMargin: `${theme.spacing(20)} 0px ${theme.spacing(20)} 0px`
  });
  const { classes } = useStyles();

  const getFirstCellElement = (): ChildNode | null | undefined =>
    rowRef.current?.firstChild?.firstChild?.firstChild;

  useEffect(() => {
    setElement(getFirstCellElement() as HTMLDivElement);
  }, [getFirstCellElement()]);

  return (
    <div className={classes.intersectionRow} ref={rowRef}>
      <Row {...props} isInViewport={isInViewport} />
    </div>
  );
};

export default IntersectionRow;
