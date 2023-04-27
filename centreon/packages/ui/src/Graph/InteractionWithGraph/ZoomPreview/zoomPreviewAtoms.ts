import { atom } from 'jotai';

import { ZoomBoundaries } from './models';

export const ZoomParametersAtom = atom<ZoomBoundaries | null>(null);
