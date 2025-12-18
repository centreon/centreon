import { atom } from 'jotai';

import { Configuration } from '../models';
import { ModalState } from './models';

export const configurationAtom = atom<Configuration | null>({
  api: { adapter: null, endpoints: null },
  defaultSelectedColumnIds: [],
  filtersInitialValues: { name: '' },
  resourceType: null
});

export const modalStateAtom = atom<ModalState>({
  id: null,
  isOpen: false,
  mode: 'add'
});

export const isFormDirtyAtom = atom<boolean>(false);
export const isCloseConfirmationDialogOpenAtom = atom<boolean>(false);
