import { atom } from 'jotai';

// Default number of items per page in the configuration listings, from the
// "Limit per page (default)" platform setting. 10 until the platform
// parameters are loaded.
export const configurationLimitPerPageAtom = atom(10);
