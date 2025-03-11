import { atom } from 'jotai';
import { ModalState } from './models';

export const ModalStateAtom = atom<ModalState>({ isOpen: false, mode: 'add' });

export const tokensToDeleteAtom = atom<Array<string>>([]);
