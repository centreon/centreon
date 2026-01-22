import { atom } from 'jotai';

export const browserLocaleAtom = atom(navigator.language.slice(0, 2));
