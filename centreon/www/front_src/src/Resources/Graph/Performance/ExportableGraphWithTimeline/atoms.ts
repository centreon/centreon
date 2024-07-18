import { atom } from 'jotai';

import { UpdatedGraphInterval } from './models';

export const updatedGraphIntervalAtom = atom<UpdatedGraphInterval | null>(null);
