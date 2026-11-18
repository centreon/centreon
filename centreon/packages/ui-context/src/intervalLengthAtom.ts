import { atom } from 'jotai';

// Seconds in one unit of the engine's check and notification intervals,
// which are stored as multiples of it. 60 is the engine's own default.
export const intervalLengthAtom = atom(60);
