import { useAtomValue } from 'jotai/utils';
import { isNil } from 'ramda';

import { Column, TableStyle as Style } from './models';
import { tableStyleAtom } from './TableAtom';

interface TableStyle {
  checkable?: boolean;
  currentVisibleColumns?: Array<Column>;
}

interface Table {
  dataStyle: Style;
  getGridTemplateColumn: string;
}

const useStyleTable = ({
  checkable,
  currentVisibleColumns
}: TableStyle): Table => {
  const dataStyle = useAtomValue(tableStyleAtom);

  const getGridTemplateColumn = (): string => {
    const checkbox = checkable ? 'min-content ' : '';

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

  return { dataStyle, getGridTemplateColumn: getGridTemplateColumn() };
};

export default useStyleTable;
