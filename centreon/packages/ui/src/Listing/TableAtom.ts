import { atom } from 'jotai';
import { equals } from 'ramda';

import { ResourceStatusViewMode as ViewMode } from '@centreon/ui-context';

import { TableStyleAtom } from './models';

const compactTableBody = {
  fontSize: '0.75rem',
  height: 30
};

const extendedTableBody = {
  fontSize: '0.85rem',
  height: 38
};

export const tableStyleAtom = atom<TableStyleAtom>({
  body: compactTableBody,
  header: {
    backgroundColor: '#666666',
    color: 'white',
    height: 38
  },
  statusColumnChip: {
    height: 20,
    width: 80
  }
});

export const tableStyleDerivedAtom = atom(null, (get, set, { viewMode }) => {
  const tableStyle = get(tableStyleAtom);

  if (equals(viewMode, ViewMode.extended)) {
    set(tableStyleAtom, {
      ...tableStyle,
      body: { ...extendedTableBody }
    });
  } else {
    set(tableStyleAtom, {
      ...tableStyle,
      body: { ...compactTableBody }
    });
  }
});
