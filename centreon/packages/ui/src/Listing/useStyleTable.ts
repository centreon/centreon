import { useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNil, pick, type } from 'ramda';
import { CSSObject } from 'tss-react';

import { Theme } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { Column, TableStyleAtom as Style } from './models';
import { tableStyleAtom, tableStyleDerivedAtom } from './tableAtoms';

interface TableStyle {
  checkable?: boolean;
  currentVisibleColumns?: Array<Column>;
  viewMode?: ListingVariant;
}

interface TableStyleState {
  dataStyle: Style;
  getGridTemplateColumn: string;
}

const isCompactMode = equals<ListingVariant | undefined>(
  ListingVariant.compact
);

interface GetTextStyleProps {
  theme: Theme;
  viewMode?: ListingVariant;
}

export const getTextStyleByViewMode = ({
  viewMode,
  theme
}: GetTextStyleProps): CSSObject =>
  pick(
    ['color', 'fontSize', 'lineHeight'],
    theme.typography[isCompactMode(viewMode) ? 'body2' : 'body1']
  );

const useStyleTable = ({
  checkable,
  currentVisibleColumns,
  viewMode
}: TableStyle): TableStyleState => {
  const dataStyle = useAtomValue(tableStyleAtom);

  const updateStyleTable = useSetAtom(tableStyleDerivedAtom);

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

        return equals(type(width), 'Number') ? `${width}px` : width;
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
