import { atom } from 'jotai';

import { Interval } from './models';

export const zoomParametersAtom = atom<Interval | null>(null);
