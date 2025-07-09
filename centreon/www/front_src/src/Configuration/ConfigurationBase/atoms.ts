import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { Configuration, Filters } from '../models';
import { columnsAtomKey, filtersAtomKey } from './constants';
import { ModalState } from './models';

export const configurationAtom = atom<Configuration | null>({
  resourceType: null,
  api: { endpoints: null, adapter: null },
  filtersInitialValues: { name: '' },
  defaultSelectedColumnIds: []
});

export const filtersAtom = atomWithStorage<Filters>(filtersAtomKey, {
  name: ''
});

export const selectedColumnIdsAtom = atomWithStorage(columnsAtomKey, []);

export const modalStateAtom = atom<ModalState>({
  id: null,
  isOpen: false,
  mode: 'add'
});

export const isFormDirtyAtom = atom<boolean>(false);
export const isCloseConfirmationDialogOpenAtom = atom<boolean>(false);
