import { SelectEntry } from '@centreon/ui';
import { atom } from 'jotai';
import { AgentType, FiltersType } from './models';
import { defaultSelectedColumnIds, filtersDefaultValue } from './utils';

import { atomWithStorage } from 'jotai/utils';
import { columnsAtomKey, filtersAtomKey } from './constants';

export const pageAtom = atom(0);
export const limitAtom = atom(10);

export const sortOrderAtom = atom('asc');
export const sortFieldAtom = atom('name');

export const filtersAtom = atomWithStorage<FiltersType>(
  filtersAtomKey,
  filtersDefaultValue
);
export const itemToDeleteAtom = atom<{
  agent: SelectEntry;
  poller?: SelectEntry;
} | null>(null);
export const agentTypeFormAtom = atom<AgentType | null>(null);
export const openFormModalAtom = atom<number | 'add' | null>(null);
export const askBeforeCloseFormModalAtom = atom(false);

export const changeSortAtom = atom(
  null,
  (_get, set, { sortOrder, sortField }) => {
    set(sortOrderAtom, sortOrder);
    set(sortFieldAtom, sortField);
  }
);

export const selectedColumnIdsAtom = atomWithStorage(
  columnsAtomKey,
  defaultSelectedColumnIds
);
