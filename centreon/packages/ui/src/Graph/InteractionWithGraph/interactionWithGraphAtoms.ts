import { atom } from 'jotai';

export const eventMouseMovingAtom = atom<null | MouseEvent>(null);

export const eventMouseDownAtom = atom<null | MouseEvent>(null);

export const eventMouseUpAtom = atom<null | MouseEvent>(null);
