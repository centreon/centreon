import { atom } from 'jotai';
import { Configuration } from './models';

export const configurationAtom = atom<Configuration | null>({
  resourceType: null,
  api: { endpoints: null },
  filtersInitialValues: {},
  defaultSelectedColumnIds: []
});

export const filtersAtom = atom({});
