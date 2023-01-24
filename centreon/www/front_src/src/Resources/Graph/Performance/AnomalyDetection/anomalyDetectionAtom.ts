import { atom } from 'jotai';

import { ExclusionPeriodsThreshold } from './models';

export const countedRedCirclesAtom = atom<number | null>(null);
export const showModalAnomalyDetectionAtom = atom<boolean>(false);

export const exclusionPeriodsThresholdAtom = atom<ExclusionPeriodsThreshold>({
  data: [],
  selectedDateToDelete: []
});
