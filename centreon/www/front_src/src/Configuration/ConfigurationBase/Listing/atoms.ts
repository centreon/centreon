import { defaultLimitPerPageAtom } from '@centreon/ui-context';

import { atom } from 'jotai';

type SortOrder = 'asc' | 'desc';

interface NamedEntity {
  id: number;
  name: string;
}

// The page size the user picked in a listing, for the session.
export const limitAtom = atom<number | undefined>(undefined);
// The page size the listings use: the user's, else the platform's. `null`
// while the platform parameters are not known yet.
export const listingLimitAtom = atom(
  (get) => get(limitAtom) ?? get(defaultLimitPerPageAtom)
);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');

export const selectedRowsAtom = atom([]);

export const resourcesToDeleteAtom = atom<Array<NamedEntity>>([]);
export const resourcesToDuplicateAtom = atom<Array<NamedEntity>>([]);
