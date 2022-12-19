import { atom } from 'jotai';

import { CellHeader } from '../models';

export const hoveredHeaderAtom = atom<CellHeader | null>(null);
