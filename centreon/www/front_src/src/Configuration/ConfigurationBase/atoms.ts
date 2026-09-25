import { atom } from 'jotai';

import { Configuration } from '../models';
import { FormActions, FormState } from './models';

export const configurationAtom = atom<Configuration | null>({
  api: { endpoints: null },
  defaultSelectedColumnIds: [],
  filtersInitialValues: { name: '' },
  resourceType: null
});

export const formStateAtom = atom<FormState>({
  id: null,
  isOpen: false,
  mode: 'add'
});

export const formActionsAtom = atom<FormActions | null>(null);

export const isFormDirtyAtom = atom<boolean>(false);
export const isCloseConfirmationDialogOpenAtom = atom<boolean>(false);
