import { atom } from 'jotai';

import { Line } from '../common/timeSeries/models';

export const linesGraphAtom = atom<Array<Line> | null>(null);
export const timeTickGraphAtom = atom<Date | null>(null);
