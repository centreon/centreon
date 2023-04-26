import { atom } from 'jotai';
import { equals } from 'ramda';

import { ListingVariant } from '@centreon/ui-context';

import { TableStyleAtom } from './models';

const compactTableBody = {
  fontSize: '0.75rem',
  height: 30
};

const extendedTableBody = {
  fontSize: '0.85rem',
  height: 38
};

const compactTableHeader = {
  height: 30
};

const extendedTableHeader = {
  height: 38
};

export const tableStyleAtom = atom<TableStyleAtom>({
  body: compactTableBody,
  header: compactTableHeader,
  statusColumnChip: {
    height: 20,
    width: 80
  }
});

export const tableStyleDerivedAtom = atom(null, (get, set, { viewMode }) => {
  const tableStyle = get(tableStyleAtom);

  const isExtendedMode = equals(viewMode, ListingVariant.extended);

  set(tableStyleAtom, {
    ...tableStyle,
    body: isExtendedMode ? extendedTableBody : compactTableBody,
    header: isExtendedMode ? extendedTableHeader : compactTableHeader
  });
});
