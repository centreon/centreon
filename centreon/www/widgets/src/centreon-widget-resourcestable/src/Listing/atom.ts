import { atom } from 'jotai';

import { ResourceListing, SortOrder } from './models';
import { defaultSelectedColumnIds } from './Columns';

export const listingAtom = atom<ResourceListing | undefined>(undefined);
export const limitAtom = atom(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>(SortOrder.Desc);
export const sortFieldAtom = atom<string>('name');

export const selectedColumnIdsAtom = atom(defaultSelectedColumnIds);
