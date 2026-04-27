import { atom } from 'jotai';

export const generatedCommandAtom = atom<string | null>(null);
export const pollerIdAtom = atom<number | null>(null);

export const isGeneratedAtom = atom(
  (get) => get(generatedCommandAtom) !== null
);

export const isModalOpenAtom = atom(false);
