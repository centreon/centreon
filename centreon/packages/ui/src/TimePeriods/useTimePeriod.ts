import { useAtomValue, useSetAtom } from 'jotai';
import { useEffect } from 'react';

import type { WrapperTimePeriodProps } from './models';
import {
  adjustTimePeriodDerivedAtom,
  customTimePeriodAtom,
  errorTimePeriodAtom,
  getDatesDerivedAtom,
  selectedTimePeriodAtom
} from './timePeriodsAtoms';

const useTimePeriod = ({
  getParameters,
  getIsError,
  adjustTimePeriodData
}: Omit<WrapperTimePeriodProps, 'extraTimePeriods' | 'disabled'>): void => {
  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const getCurrentEndStartInterval = useAtomValue(getDatesDerivedAtom);
  const errorTimePeriod = useAtomValue(errorTimePeriodAtom);
  const adjustTimeTimePeriod = useSetAtom(adjustTimePeriodDerivedAtom);

  useEffect(() => {
    if (!adjustTimePeriodData) {
      return;
    }

    adjustTimeTimePeriod(adjustTimePeriodData);
  }, [
    adjustTimePeriodData?.start,
    adjustTimePeriodData?.end,
    adjustTimePeriodData,
    adjustTimeTimePeriod
  ]);

  useEffect(() => {
    if (customTimePeriod) {
      getParameters?.({
        end: customTimePeriod.end.toISOString(),
        start: customTimePeriod.start.toISOString(),
        timelineEventsLimit: customTimePeriod.timelineEventsLimit
      });
      return;
    }
    const [start, end, timelineEventsLimit] =
      getCurrentEndStartInterval(selectedTimePeriod);

    getParameters?.({ end, start, timelineEventsLimit });
  }, [selectedTimePeriod, customTimePeriod]);

  useEffect(() => {
    if (!errorTimePeriod) {
      return;
    }
    getIsError?.(errorTimePeriod);
  }, [errorTimePeriod, getIsError]);
};

export default useTimePeriod;
