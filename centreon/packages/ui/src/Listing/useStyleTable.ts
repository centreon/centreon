import { useEffect, useMemo } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isEmpty, isNil, pick, type, update } from 'ramda';
import { CSSObject } from 'tss-react';

import { Theme } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { Column, TableStyleAtom as Style } from './models';
import { tableStyleAtom, tableStyleDerivedAtom } from './tableAtoms';

interface TableStyle {
  listingVariant?: ListingVariant;
}

interface TableStyleState {
  dataStyle: Style;
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

const useStyleTable = ({ listingVariant }: TableStyle): TableStyleState => {
  const dataStyle = useAtomValue(tableStyleAtom);

  const updateStyleTable = useSetAtom(tableStyleDerivedAtom);

  useEffect(() => {
    if (listingVariant) {
      updateStyleTable({ listingVariant });
    }
  }, [listingVariant]);

  return {
    dataStyle
  };
};

export default useStyleTable;

interface UseColumnStyleProps {
  checkable?: boolean;
  currentVisibleColumns?: Array<Column>;
}

export const useColumnStyle = ({
  checkable,
  currentVisibleColumns
}: UseColumnStyleProps): string => {
  const gridTemplateColumn = useMemo((): string => {
    const checkbox = checkable ? 'fit-content(1rem) ' : ''; // SelectAction (checkbox) cell adjusts to content

    const columnTemplate: Array<string> =
      currentVisibleColumns
        ?.filter((column) => column)
        ?.map(({ width, shortLabel }) => {
          if (!isNil(shortLabel)) {
            return 'min-content';
          }
          if (isNil(width)) {
            return 'auto';
          }

          return (
            equals(type(width), 'Number') ? `${width}px` : width
          ) as string;
        }) || [];

    const hasOnlyContainerResponsiveColumns =
      !isEmpty(columnTemplate) &&
      columnTemplate.every(
        (width: string) =>
          width.includes('auto') ||
          width.includes('fr') ||
          width.includes('%') ||
          width.includes('px')
      );

    if (!hasOnlyContainerResponsiveColumns) {
      const fixedColumnTemplate = update(
        columnTemplate.length - 1,
        'auto',
        columnTemplate
      );

      return `${checkbox}${fixedColumnTemplate.join(' ')}`;
    }

    return `${checkbox}${columnTemplate.join(' ')}`;
  }, [checkable, currentVisibleColumns]);

  return gridTemplateColumn;
};
