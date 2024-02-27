import { useState, useMemo, useEffect } from 'react';

import {
  concat,
  differenceWith,
  equals,
  filter,
  findIndex,
  gt,
  includes,
  isNil,
  last,
  length,
  lt,
  map,
  not,
  pluck,
  reduce,
  reject,
  slice,
  subtract,
  uniqBy
} from 'ramda';
import { useAtomValue } from 'jotai';

import { useKeyObserver } from '../..';

import { RowId } from './models';
import { subItemsPivotsAtom } from './tableAtoms';

interface GetSelectedRowsWithShiftKeyProps {
  compareFunction;
  comparisonSliceEndIndex: number;
  comparisonSliceStartIndex: number;
  newSelection: Array<unknown>;
  selectedRowIndex: number;
  selectedRowsIndex: Array<number>;
}

interface UseListingProps {
  currentPage;
  disableRowCheckCondition;
  getId;
  limit;
  onSelectColumns;
  onSelectRows;
  rows;
  selectedRows;
  subItems;
  totalRows;
}

interface UseListingState {
  allSubItemIds;
  areColumnsEditable;
  clearHoveredRow;
  emptyRows;
  hoverRow;
  hoveredRowId;
  isSelected;
  isShiftKeyDown;
  lastSelectionIndex;
  onSelectRowsWithCondition;
  rowsToDisplay;
  selectAllRows;
  selectRow;
  shiftKeyDownRowPivot;
  subItemsPivots;
}

const useListing = <TRow>({
  currentPage,
  onSelectRows,
  totalRows,
  rows,
  subItems,
  limit,
  getId,
  selectedRows,
  disableRowCheckCondition,
  onSelectColumns
}: UseListingProps): UseListingState => {
  const [hoveredRowId, setHoveredRowId] = useState<RowId | null>(null);
  const [shiftKeyDownRowPivot, setShiftKeyDownRowPivot] = useState<
    number | null
  >(null);
  const [lastSelectionIndex, setLastSelectionIndex] = useState<number | null>(
    null
  );

  const subItemsPivots = useAtomValue(subItemsPivotsAtom);

  const rowsToDisplay = useMemo(
    () =>
      subItems?.enable
        ? reduce<TRow, Array<TRow>>(
            (acc, row): Array<TRow> => {
              if (
                row[subItems.getRowProperty()] &&
                subItemsPivots.includes(row.id)
              ) {
                return [...acc, row, ...row[subItems.getRowProperty()]];
              }

              return [...acc, row];
            },
            [],
            rows
          )
        : rows,
    [rows, subItemsPivots, subItems]
  );

  const { isShiftKeyDown } = useKeyObserver();

  const haveSameId = (row: TRow, rowToCompare: TRow): boolean =>
    equals(getId(row), getId(rowToCompare));

  const selectedRowsInclude = (row): boolean => {
    return !!selectedRows.find((includedRow) =>
      equals(getId(includedRow), getId(row))
    );
  };

  const selectAllRows = (event): void => {
    if (
      event.target.checked &&
      event.target.getAttribute('data-indeterminate') === 'false'
    ) {
      onSelectRows(reject(disableRowCheckCondition, rows));
      setLastSelectionIndex(null);

      return;
    }

    onSelectRows([]);
    setLastSelectionIndex(null);
  };

  const onSelectRowsWithCondition = (condition: (row) => boolean): void => {
    onSelectRows(reject(disableRowCheckCondition, filter(condition, rows)));
    setLastSelectionIndex(null);
  };

  const getSelectedRowsWithShiftKey = ({
    newSelection,
    selectedRowsIndex,
    selectedRowIndex,
    compareFunction,
    comparisonSliceStartIndex,
    comparisonSliceEndIndex
  }: GetSelectedRowsWithShiftKeyProps): Array<TRow> => {
    if (includes(selectedRowIndex, selectedRowsIndex)) {
      return differenceWith(haveSameId, selectedRows, newSelection);
    }
    if (
      compareFunction(lastSelectionIndex, last(selectedRowsIndex) as number)
    ) {
      const normalizedNewSelection = slice(
        comparisonSliceStartIndex,
        comparisonSliceEndIndex,
        newSelection
      );

      const newSelectionWithCurrentSelection = concat(
        selectedRows,
        normalizedNewSelection
      );

      const newSelectedRowsWithUniqElements = uniqBy(
        getId,
        newSelectionWithCurrentSelection
      );

      return newSelectedRowsWithUniqElements;
    }
    const newSelectedRowsWithCurrentSelection = concat(
      selectedRows,
      newSelection
    );

    const newSelectedRowsWithUniqElements = uniqBy(
      getId,
      newSelectedRowsWithCurrentSelection
    );

    return newSelectedRowsWithUniqElements;
  };

  const selectRowsWithShiftKey = (selectedRowIndex: number): void => {
    const lastSelectedIndex = lastSelectionIndex as number;
    if (isNil(shiftKeyDownRowPivot)) {
      const selectedRowsFromTheStart = slice(0, selectedRowIndex + 1, rows);

      onSelectRows(reject(disableRowCheckCondition, selectedRowsFromTheStart));

      return;
    }

    const selectedRowsIndex = map(
      (row) =>
        findIndex((listingRow) => equals(getId(row), getId(listingRow)), rows),
      selectedRows
    ).sort(subtract);

    if (selectedRowIndex < lastSelectedIndex) {
      const newSelection = slice(
        selectedRowIndex,
        (lastSelectionIndex as number) + 1,
        rows
      );
      onSelectRows(
        reject(
          disableRowCheckCondition,
          getSelectedRowsWithShiftKey({
            compareFunction: gt,
            comparisonSliceEndIndex: -1,
            comparisonSliceStartIndex: 0,
            newSelection,
            selectedRowIndex,
            selectedRowsIndex
          })
        )
      );

      return;
    }

    const newSelection = slice(lastSelectedIndex, selectedRowIndex + 1, rows);
    onSelectRows(
      reject(
        disableRowCheckCondition,
        getSelectedRowsWithShiftKey({
          compareFunction: lt,
          comparisonSliceEndIndex: length(newSelection),
          comparisonSliceStartIndex: 1,
          newSelection,
          selectedRowIndex,
          selectedRowsIndex
        })
      )
    );
  };

  const selectRow = (event: React.MouseEvent, row): void => {
    event.preventDefault();
    event.stopPropagation();
    // This prevents unwanted text selection
    document.getSelection()?.removeAllRanges();

    const selectedRowIndex = findIndex(
      (listingRow) => equals(getId(row), getId(listingRow)),
      rows
    );

    if (isShiftKeyDown) {
      selectRowsWithShiftKey(selectedRowIndex);
      setLastSelectionIndex(selectedRowIndex);

      return;
    }

    setLastSelectionIndex(selectedRowIndex);

    if (disableRowCheckCondition(row)) {
      return;
    }

    if (selectedRowsInclude(row)) {
      onSelectRows(
        selectedRows.filter((entity) => !equals(getId(entity), getId(row)))
      );

      return;
    }
    onSelectRows([...selectedRows, row]);
  };

  const hoverRow = (row): void => {
    if (equals(hoveredRowId, getId(row))) {
      return;
    }
    setHoveredRowId(getId(row));
  };

  const clearHoveredRow = (): void => {
    setHoveredRowId(null);
  };

  const isSelected = (row): boolean => {
    return selectedRowsInclude(row);
  };

  const emptyRows = limit - Math.min(limit, totalRows - currentPage * limit);

  useEffect(() => {
    if (not(isShiftKeyDown)) {
      setShiftKeyDownRowPivot(null);

      return;
    }
    setShiftKeyDownRowPivot(lastSelectionIndex);
  }, [isShiftKeyDown, lastSelectionIndex]);

  const areColumnsEditable = not(isNil(onSelectColumns));

  const allSubItemIds = useMemo(
    () =>
      reduce<TRow | number, Array<string | number>>(
        (acc, row) => [
          ...acc,
          ...pluck('id', row[subItems?.getRowProperty() || ''] || [])
        ],
        [],
        rows
      ),
    [rows, subItems]
  );

  return {
    allSubItemIds,
    areColumnsEditable,
    clearHoveredRow,
    emptyRows,
    hoverRow,
    hoveredRowId,
    isSelected,
    isShiftKeyDown,
    lastSelectionIndex,
    onSelectRowsWithCondition,
    rowsToDisplay,
    selectAllRows,
    selectRow,
    shiftKeyDownRowPivot,
    subItemsPivots
  };
};

export default useListing;
