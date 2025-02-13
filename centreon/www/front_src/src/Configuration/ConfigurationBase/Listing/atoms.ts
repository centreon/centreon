import { atom } from 'jotai';

import { atomWithReset } from 'jotai/utils';

type SortOrder = 'asc' | 'desc';

interface NamedEntity {
  id: number;
  name: string;
}

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');

export const searchAtom = atom<string>('');

export const selectedRowsAtom = atom([]);

export const resourcesToDeleteAtom = atom<Array<NamedEntity>>([]);
export const resourcesToDuplicateAtom = atom<Array<NamedEntity>>([]);

export const tooltipPageAtom = atomWithReset(1);
