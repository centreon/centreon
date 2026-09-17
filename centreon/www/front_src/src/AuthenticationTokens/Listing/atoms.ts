import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { baseKey } from '../storage';
import { defaultSelectedColumnIds } from './Columns/Columns';
import { Token } from './models';

type SortOrder = 'asc' | 'desc';

export const selectedColumnIdsAtom = atomWithStorage(
  `${baseKey}_column-ids`,
  defaultSelectedColumnIds
);

export const selectedRowAtom = atom<Token | null>(null);

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');
