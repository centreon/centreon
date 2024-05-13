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
  listingVariant?: ListingVariant;
}

interface TableStyleState {
  dataStyle: Style;
  getGridTemplateColumn: string;
}

const isCompactMode = equals<ListingVariant | undefined>(
  ListingVariant.compact
);

interface GetTextStyleProps {
  listingVariant?: ListingVariant;
  theme: Theme;
}

export const getTextStyleByViewMode = ({
  listingVariant,
  theme
}: GetTextStyleProps): CSSObject =>
  pick(
    ['color', 'fontSize', 'lineHeight'],
    theme.typography[isCompactMode(listingVariant) ? 'body2' : 'body1']
  );

const useStyleTable = ({
  checkable,
  currentVisibleColumns,
  listingVariant
}: TableStyle): TableStyleState => {
  const dataStyle = useAtomValue(tableStyleAtom);

  const updateStyleTable = useSetAtom(tableStyleDerivedAtom);

  const getGridTemplateColumn = (): string => {
    const checkbox = checkable ? 'fit-content(1rem) ' : ''; // SelectAction (checkbox) cell adjusts to content

    const columnTemplate = currentVisibleColumns
      ?.filter((column) => column)
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
    if (listingVariant) {
      updateStyleTable({ listingVariant });
    }
  }, [listingVariant]);

  return {
    dataStyle,
    getGridTemplateColumn: getGridTemplateColumn()
  };
};

export default useStyleTable;
