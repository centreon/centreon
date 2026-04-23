import { atom } from 'jotai';

export const generatedCommandAtom = atom<string | null>(null);
export const pollerIdAtom = atom<number | null>(null);

// re-check later
export const isGeneratedAtom = atom(
  (get) => get(generatedCommandAtom) !== null
);
