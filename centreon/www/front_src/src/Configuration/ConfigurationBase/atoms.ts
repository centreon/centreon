import { atom } from 'jotai';
import { Configuration, Filters } from '../models';
import { ModalState } from './models';

export const configurationAtom = atom<Configuration | null>({
  resourceType: null,
  api: { endpoints: null, adapter: null },
  filtersInitialValues: { name: '' },
  defaultSelectedColumnIds: []
});

export const filtersAtom = atom<Filters>({ name: '' });

export const modalStateAtom = atom<ModalState>({
  id: null,
  isOpen: false,
  mode: 'add'
});

export const isFormDirtyAtom = atom<boolean>(false);
export const isCloseConfirmationDialogOpenAtom = atom<boolean>(false);
