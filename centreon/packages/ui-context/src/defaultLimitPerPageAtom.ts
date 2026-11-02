import { atom } from 'jotai';

// The platform's default page size for listings, from the platform
// parameters. `null` until they are known.
export const defaultLimitPerPageAtom = atom<number | null>(null);
