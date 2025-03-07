import { atom } from 'jotai';
import { DialogState } from './models';

export const dialogStateAtom = atom<DialogState>({
  id: null,
  isOpen: false,
  variant: 'create'
});
