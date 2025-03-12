import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { Filter, ModalState } from './models';
import { baseKey } from './storage';
import { filtersInitialValues } from './utils';

export const ModalStateAtom = atom<ModalState>({ isOpen: false, mode: 'add' });

export const tokensToDeleteAtom = atom<Array<string>>([]);

export const filtersAtom = atomWithStorage<Filter>(
  `${baseKey}_filters`,
  filtersInitialValues
);
