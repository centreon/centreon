import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import type { Filters } from './models';
import { columnsAtomKey, filtersAtomKey, filtersInitialValues } from './utils';

export const selectedColumnIdsAtom = atomWithStorage<Array<string>>(
  columnsAtomKey,
  []
);

export const filtersAtom = atomWithStorage<Filters>(
  filtersAtomKey,
  filtersInitialValues
);

export const isWelcomePageDisplayedAtom = atom<boolean>(true);

// The services of the mass change selection that the listing shows on several
// hosts: saving one keeps only its first host.
export const sharedServiceIdsAtom = atom<Array<number>>([]);
