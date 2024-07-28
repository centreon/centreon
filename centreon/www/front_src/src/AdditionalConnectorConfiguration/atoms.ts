import { atom } from 'jotai';

import { DialogState } from './Listing/models';

export const dialogStateAtom = atom<DialogState>({
  connector: null,
  isOpen: false,
  variant: 'create'
});

export const isFormDirtyAtom = atom<boolean>(false);
export const isCloseModalDialogOpenAtom = atom<boolean>(false);
