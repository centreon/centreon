import { atomWithStorage } from 'jotai/utils';
import { columnsAtomKey, filtersAtomKey, filtersInitialValues } from './utils';

export const selectedColumnIdsAtom = atomWithStorage(columnsAtomKey, []);
export const filtersAtom = atomWithStorage(
  filtersAtomKey,
  filtersInitialValues
);
