import { atom } from 'jotai';
import { atomWithReset, atomWithStorage } from 'jotai/utils';

import { AiProviderType, Filters } from './models';
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

// models discovered via the "fetch models" button, tagged with the provider
// type they were fetched for so they are not offered for a different type;
// reset whenever the form opens on another provider
export const discoveredModelsAtom = atomWithReset<{
  models: Array<string>;
  type: AiProviderType | null;
}>({ models: [], type: null });
