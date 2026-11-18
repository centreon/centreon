import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import type { ResourceRow } from '../models';
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

// The listing rows a mass change was opened on, with their hosts.
export const massChangeRowsAtom = atom<Array<ResourceRow>>([]);
