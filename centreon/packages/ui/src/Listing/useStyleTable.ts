import { useEffect } from 'react';

import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { equals, isNil } from 'ramda';

import {
  userAtom,
  ResourceStatusViewMode,
  ThemeMode
} from '@centreon/ui-context';

import { Column, TableStyleAtom as Style } from './models';
import { tableStyleAtom, tableStyleDerivedAtom } from './TableAtom';

interface TableStyle {
  checkable?: boolean;
  currentVisibleColumns?: Array<Column>;
  severityCode?: number;
  viewMode?: ResourceStatusViewMode;
}

interface Table {
  colorBodyCell: string;
  dataStyle: Style;
  getGridTemplateColumn: string;
}

const useStyleTable = ({
  checkable,
  currentVisibleColumns,
  viewMode,
  severityCode
}: TableStyle): Table => {
  const dataStyle = useAtomValue(tableStyleAtom);
  const { themeMode } = useAtomValue(userAtom);

  const updateStyleTable = useUpdateAtom(tableStyleDerivedAtom);

  const getBodyTextColor = ({ theme, severityCode: code }): string => {
    if (equals(ThemeMode.dark, theme)) {
      if (equals(code, 1)) {
        return '#FFFFFF';
      }

      return '#B5B5B5';
    }

    if (equals(code, 1)) {
      return '#000000';
    }

    return '#666666';
  };

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
    colorBodyCell: getBodyTextColor({
      severityCode,
      theme: themeMode
    }),
    dataStyle,
    getGridTemplateColumn: getGridTemplateColumn()
  };
};

export default useStyleTable;
