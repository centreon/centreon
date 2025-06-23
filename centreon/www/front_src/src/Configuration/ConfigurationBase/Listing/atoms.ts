import { atom } from 'jotai';

type SortOrder = 'asc' | 'desc';

interface NamedEntity {
  id: number;
  name: string;
}

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');

export const selectedRowsAtom = atom([]);

export const resourcesToDeleteAtom = atom<Array<NamedEntity>>([]);
export const resourcesToDuplicateAtom = atom<Array<NamedEntity>>([]);
