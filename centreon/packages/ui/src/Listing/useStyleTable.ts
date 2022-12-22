import { useAtomValue } from 'jotai/utils';
import { isNil } from 'ramda';

import { headerAtom } from './Header/headerAtom';

const useStyleTable = ({ checkable, currentVisibleColumns }: any): any => {
  const headerData = useAtomValue(headerAtom);

  const getGridTemplateColumn = (): string => {
    const checkbox = checkable ? 'min-content ' : '';

    const columnTemplate = currentVisibleColumns
      .map(({ width, shortLabel }) => {
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

  return { getGridTemplateColumn: getGridTemplateColumn(), headerData };
};

export default useStyleTable;
