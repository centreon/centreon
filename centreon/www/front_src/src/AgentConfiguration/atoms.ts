import { SelectEntry } from '@centreon/ui';

import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { equals, findIndex, remove } from 'ramda';

import { AgentType } from './models';
import {
  baseKey,
  defaultSelectedColumnIds,
  filtersInitialValues
} from './utils';

export const pageAtom = atom(0);
export const limitAtom = atom(10);

export const sortOrderAtom = atom('asc');
export const sortFieldAtom = atom('name');

export const itemToDeleteAtom = atom<{
  agent: SelectEntry;
  poller?: SelectEntry;
} | null>(null);
export const agentTypeFormAtom = atom<AgentType | null>(null);
export const openFormModalAtom = atom<number | 'add' | null>(null);
export const askBeforeCloseFormModalAtom = atom(false);

export const pollerToGenerateCommanAtom = atom<{
  id?: number;
  name?: string;
} | null>(null);

export const changeSortAtom = atom(
  null,
  (_get, set, { sortOrder, sortField }) => {
    set(sortOrderAtom, sortOrder);
    set(sortFieldAtom, sortField);
  }
);

interface ChangeFilterProps {
  field: string;
  newEntries: Array<SelectEntry>;
}

export const changeFilterAtom = atom(
  null,
  (get, set, { field, newEntries }: ChangeFilterProps) => {
    set(filtersAtom, {
      ...get(filtersAtom),
      [field]: newEntries
    });
    set(pageAtom, 0);
  }
);

interface DeleteFilterProps {
  field: string;
  entryToDelete: SelectEntry;
}

export const deleteFilterEntryAtom = atom(
  null,
  (get, set, { field, entryToDelete }: DeleteFilterProps) => {
    const fieldEntries = get(filtersAtom)[field];

    const entryToDeleteIndex = findIndex(
      ({ id }) => equals(entryToDelete.id, id),
      fieldEntries
    );

    set(filtersAtom, {
      ...get(filtersAtom),
      [field]: remove(entryToDeleteIndex, 1, fieldEntries)
    });
    set(pageAtom, 0);
  }
);

export const isWelcomePageDisplayedAtom = atom<boolean>(true);

export const filtersAtom = atomWithStorage(
  `${baseKey}_filters`,
  filtersInitialValues
);

export const selectedColumnIdsAtom = atomWithStorage(
  `${baseKey}_column-ids`,
  defaultSelectedColumnIds
);
