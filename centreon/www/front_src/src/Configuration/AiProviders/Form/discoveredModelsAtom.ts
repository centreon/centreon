import { atom } from 'jotai';

// models discovered via the "fetch models" button, tagged with the provider
// type they were fetched for so they are not offered for a different type
export const discoveredModelsAtom = atom<{
  models: Array<string>;
  type: string | null;
}>({ models: [], type: null });
