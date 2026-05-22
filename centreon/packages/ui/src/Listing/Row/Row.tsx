import { TableRow, type TableRowProps, useTheme } from '@mui/material';

import type { ListingVariant } from '@centreon/ui-context';

import { equals, lt, not, pluck } from 'ramda';
import { memo, useCallback, useEffect, useRef } from 'react';

import { useViewportIntersection } from '../../utils/useViewportIntersection';
import type { Column, ColumnConfiguration, RowColorCondition } from '../models';

type Props = {
  checkable: boolean;
  children: React.ReactNode;
  columnConfiguration?: ColumnConfiguration;
  columnIds: Array<string>;
  disableRowCondition: (row: Record<string, unknown>) => boolean;
  isHovered?: boolean;
  isSelected?: boolean;
  isShiftKeyDown: boolean;
  lastSelectionIndex: number | null;
  limit: number;
  listingVariant?: ListingVariant;
  row: Record<string, unknown>;
  rowColorConditions: Array<RowColorCondition>;
  shiftKeyDownRowPivot: number | null;
  subItemsPivots: Array<number | string>;
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
    onClick
  }: RowProps): JSX.Element => {
    return (
      <TableRow
        className="cursor-pointer contents w-full"
        component="div"
        onClick={onClick}
        onFocus={onFocus}
        onMouseOver={onMouseOver}
        tabIndex={tabIndex}
      >
        {children}
      </TableRow>
    );
  },
  (prevProps: RowProps, nextProps: RowProps) => {
    const {
      row: previousRow,
      rowColorConditions: previousRowColorConditions,
      visibleColumns: previousVisibleColumns,
      isShiftKeyDown: prevIsShiftKeyDown,
      shiftKeyDownRowPivot: prevShiftKeyDownRowPivot,
      lastSelectionIndex: prevLastSelectionIndex,
      listingVariant: prevViewMode
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
      listingVariant: nextViewMode
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

    const previousRowConditions = previousRowColorConditions?.map(
      ({ condition }) => condition(previousRow)
    );
    const nextRowConditions = nextRowColorConditions?.map(({ condition }) =>
      condition(nextRow)
    );

    const previousRowColors = previousRowColorConditions?.map(
      ({ color }) => color
    );
    const nextRowColors = nextRowColorConditions?.map(({ color }) => color);

    if (not(nextIsInViewport) && lt(nextLimit, 60)) {
      return (
        equals(prevProps.isSelected, nextProps.isSelected) &&
        equals(prevProps.row, nextProps.row) &&
        equals(previousRowConditions, nextRowConditions) &&
        equals(previousRowColors, nextRowColors) &&
        equals(prevProps.className, nextProps.className) &&
        equals(prevProps.subItemsPivots, nextProps.subItemsPivots)
      );
    }

    return (
      equals(prevProps.isSelected, nextProps.isSelected) &&
      equals(prevProps.row, nextProps.row) &&
      equals(prevProps.className, nextProps.className) &&
      equals(previousRowConditions, nextRowConditions) &&
      equals(previousRowColors, nextRowColors) &&
      equals(prevProps.columnIds, nextProps.columnIds) &&
      equals(prevProps.columnConfiguration, nextProps.columnConfiguration) &&
      equals(prevIsShiftKeyDown, nextIsShiftKeyDown) &&
      equals(prevShiftKeyDownRowPivot, nextShiftKeyDownRowPivot) &&
      equals(prevLastSelectionIndex, nextLastSelectionIndex) &&
      equals(prevViewMode, nextViewMode) &&
      equals(prevProps.subItemsPivots, nextProps.subItemsPivots)
    );
  }
);

const IntersectionRow = ({ isHovered, ...rest }: Props): JSX.Element => {
  const rowRef = useRef<HTMLDivElement | null>(null);
  const theme = useTheme();
  const { isInViewport, setElement } = useViewportIntersection({
    root: rowRef.current?.parentElement?.parentElement?.parentElement,
    rootMargin: `${theme.spacing(20)} 0px ${theme.spacing(20)} 0px`
  });

  const getFirstCellElement = useCallback(
    (): ChildNode | null | undefined =>
      rowRef.current?.firstChild?.firstChild?.firstChild,
    []
  );

  useEffect(() => {
    setElement(getFirstCellElement() as HTMLDivElement);
  }, [getFirstCellElement()]);

  return (
    <div className="contents w-full" data-is-hovered={isHovered} ref={rowRef}>
      <Row {...rest} isHovered={isHovered} isInViewport={isInViewport} />
    </div>
  );
};

export default IntersectionRow;
