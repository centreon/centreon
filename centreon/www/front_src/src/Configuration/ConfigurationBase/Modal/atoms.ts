import { atom } from 'jotai';
import { ModalState } from './models';

export const modalStateAtom = atom<ModalState>({
  id: null,
  isOpen: false,
  mode: 'add'
});
