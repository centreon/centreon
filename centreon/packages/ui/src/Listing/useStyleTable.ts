import { useEffect } from 'react';

import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { isNil } from 'ramda';

import { ListingVariant } from '@centreon/ui-context';

import { Column, TableStyleAtom as Style } from './models';
import { tableStyleAtom, tableStyleDerivedAtom } from './tableAtoms';

interface TableStyle {
  checkable?: boolean;
  currentVisibleColumns?: Array<Column>;
  viewMode?: ListingVariant;
}

interface Table {
  dataStyle: Style;
  getGridTemplateColumn: string;
}

const useStyleTable = ({
  checkable,
  currentVisibleColumns,
  viewMode
}: TableStyle): Table => {
  const dataStyle = useAtomValue(tableStyleAtom);

  const updateStyleTable = useUpdateAtom(tableStyleDerivedAtom);

  const getGridTemplateColumn = (): string => {
    const checkbox = checkable ? '50px ' : '';

    const columnTemplate = currentVisibleColumns
      ?.map(({ width, shortLabel }) => {
        if (!isNil(shortLabel)) {
          return 'min-content';
        }
        if (isNil(width)) {
          return 'auto';
        }

        return typeof width === 'number' ? `${width}px` : width;
      })
      .join(' ');

    return `${checkbox}${columnTemplate}`;
  };

  useEffect(() => {
    if (viewMode) {
      updateStyleTable({ viewMode });
    }
  }, [viewMode]);

  return {
    dataStyle,
    getGridTemplateColumn: getGridTemplateColumn()
  };
};

export default useStyleTable;
