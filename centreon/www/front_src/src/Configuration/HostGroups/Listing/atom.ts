import { atom } from 'jotai';

import { filtersDefaultValue } from './utils';

import { FiltersType } from './models';

type SortOrder = 'asc' | 'desc';

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');

export const filtersAtom = atom<FiltersType>(filtersDefaultValue);

export const searchAtom = atom<string>('');
