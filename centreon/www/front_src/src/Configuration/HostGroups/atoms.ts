import { atom } from 'jotai';
import { atomWithReset } from 'jotai/utils';
import { NamedEntity } from './Listing/models';

export const selectedRowsAtom = atom([]);

export const hostGroupsToDeleteAtom = atom<Array<NamedEntity>>([]);
export const hostGroupsToDuplicateAtom = atom<Array<NamedEntity>>([]);

export const tooltipPageAtom = atomWithReset(1);
