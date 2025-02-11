import { atom } from 'jotai';

import { atomWithReset } from 'jotai/utils';
import { NamedEntity } from './models';

type SortOrder = 'asc' | 'desc';

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');

export const searchAtom = atom<string>('');

export const selectedRowsAtom = atom([]);

export const hostGroupsToDeleteAtom = atom<Array<NamedEntity>>([]);
export const hostGroupsToDuplicateAtom = atom<Array<NamedEntity>>([]);

export const tooltipPageAtom = atomWithReset(1);
