import { atom } from 'jotai';

export const pageAtom = atom(0);
export const limitAtom = atom(10);
export const searchAtom = atom('');
export const sortOrderAtom = atom('asc');
export const sortFieldAtom = atom('name');
export const openFormModalAtom = atom<number | 'add' | null>(null);
