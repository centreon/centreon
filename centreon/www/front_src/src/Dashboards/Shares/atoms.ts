import { atom } from 'jotai';

export const selectedDashboardShareAtom = atom<number | string | undefined>(
  undefined
);
