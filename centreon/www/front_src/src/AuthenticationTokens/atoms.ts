import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { Filter, ModalState } from './models';
import { baseKey } from './storage';
import { filtersInitialValues } from './utils';

export const tokensToDeleteAtom = atom<Array<string>>([]);
export const tokensToDisableAtom = atom<Array<string>>([]);
export const tokensToEnableAtom = atom<Array<string>>([]);

export const filtersAtom = atomWithStorage<Filter>(
  `${baseKey}_filters`,
  filtersInitialValues
);

export const modalStateAtom = atom<ModalState>({ isOpen: false, mode: 'add' });
export const tokenAtom = atom<string | null>(null);
