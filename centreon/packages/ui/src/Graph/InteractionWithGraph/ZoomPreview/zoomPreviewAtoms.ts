import { atom } from 'jotai';

import { ZoomBoundaries } from './models';

export const zoomParametersAtom = atom<ZoomBoundaries | null>(null);
