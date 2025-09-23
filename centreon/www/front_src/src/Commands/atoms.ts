import { atomWithStorage } from 'jotai/utils';
import { Filters } from './models';
import { baseKey, filtersInitialValues } from './utils';

export const filtersAtom = atomWithStorage<Filters>(
  `${baseKey}_filters`,
  filtersInitialValues
);
