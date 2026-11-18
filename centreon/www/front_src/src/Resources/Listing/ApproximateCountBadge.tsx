import { atom } from 'jotai';

export const exactCountAtom = atom<number | null>(null);
export const exactCountLoadingAtom = atom<boolean>(false);
