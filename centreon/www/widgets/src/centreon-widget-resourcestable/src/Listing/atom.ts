import { atom } from 'jotai';

import { SortOrder } from './models';

export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>(SortOrder.Desc);
export const sortFieldAtom = atom<string>('name');
