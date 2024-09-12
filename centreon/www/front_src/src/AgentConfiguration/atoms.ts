import { atom } from 'jotai';
import { SelectEntry } from 'packages/ui/src';
import { findIndex, remove } from 'ramda';

export const pageAtom = atom(0);
export const limitAtom = atom(10);
export const searchAtom = atom('');
export const sortOrderAtom = atom('asc');
export const sortFieldAtom = atom('name');
export const filtersAtom = atom({
  agentTypes: [],
  pollers: []
});

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
  (get, set, { field, newValue }: ChangeFilterProps) => {
    set(filtersAtom, {
      ...get(filtersAtom),
      [field]: newValue
    });
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
  }
);
