import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { baseKey } from '../storage';

import { defaultSelectedColumnIds } from './ComponentsColumn/models';
import { Filter, SortOrder, Token } from './models';
import { filtersInitialValues } from './utils';

export const selectedColumnIdsAtom = atomWithStorage(
  `${baseKey}column-ids`,
  defaultSelectedColumnIds
);

export const selectedRowAtom = atom<Token | null>(null);

export const filtersAtom = atomWithStorage<Filter>(
  `${baseKey}_filters`,
  filtersInitialValues
);

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');
