import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { Configuration } from './models';
import { atomKey } from './utils';

export const configurationAtom = atom<Configuration | null>({
  resourceType: null,
  api: { endpoints: null },
  filtersInitialValues: {},
  defaultSelectedColumnIds: []
});

export const filtersAtom = atom({});

export const selectedColumnIdsAtom = atomWithStorage(
  `selectedColumn_${atomKey}`,
  []
);
