import { atom } from 'jotai';
import { atomWithReset, atomWithStorage } from 'jotai/utils';

import { Filters } from './models';
import { columnsAtomKey, filtersAtomKey, filtersInitialValues } from './utils';

export const tooltipPageAtom = atomWithReset(1);

export const selectedColumnIdsAtom = atomWithStorage<Array<string>>(
  columnsAtomKey,
  []
);
export const filtersAtom = atomWithStorage<Filters>(
  filtersAtomKey,
  filtersInitialValues
);

export const isWelcomePageDisplayedAtom = atom<boolean>(true);
