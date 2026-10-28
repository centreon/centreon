import { configurationLimitPerPageAtom } from '@centreon/ui-context';

import { atom } from 'jotai';

type SortOrder = 'asc' | 'desc';

interface NamedEntity {
  id: number;
  name: string;
}

// The limit the user picked, if any.
const selectedLimitAtom = atom<number | undefined>(undefined);

// Until the user picks a limit, follow the platform "Limit per page" setting.
export const limitAtom = atom(
  (get) => get(selectedLimitAtom) ?? get(configurationLimitPerPageAtom),
  (_get, set, limit: number | undefined) => set(selectedLimitAtom, limit)
);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');

export const selectedRowsAtom = atom([]);

export const resourcesToDeleteAtom = atom<Array<NamedEntity>>([]);
export const resourcesToDuplicateAtom = atom<Array<NamedEntity>>([]);
