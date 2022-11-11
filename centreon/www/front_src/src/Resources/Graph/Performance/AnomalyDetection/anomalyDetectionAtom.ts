import { atom } from 'jotai';

import { ThresholdsAnomalyDetectionDataAtom } from './models';

export const countedRedCirclesAtom = atom<number | null>(null);

export const showModalAnomalyDetectionAtom = atom<boolean>(false);

export const thresholdsAnomalyDetectionDataAtom =
  atom<ThresholdsAnomalyDetectionDataAtom>({
    exclusionPeriodsThreshold: {
      data: [{ isConfirmed: false, lines: [], timeSeries: [] }],
      selectedDateToDelete: [],
    },
  });
