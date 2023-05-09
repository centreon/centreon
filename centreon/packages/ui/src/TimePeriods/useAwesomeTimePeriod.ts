import { useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import {
  customTimePeriodAtom,
  getDatesDerivedAtom,
  selectedTimePeriodAtom,
  errorTimePeriodAtom,
  adjustTimePeriodDerivedAtom
} from './timePeriodsAtoms';

import { Props } from '.';

const useAwesomeTimePeriod = ({
  getParameters,
  getIsError,
  adjustTimePeriodData
}: Omit<Props, 'extraTimePeriods' | 'disabled'>): void => {
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
  }, [adjustTimePeriodData?.start, adjustTimePeriodData?.end]);

  useEffect(() => {
    const [start, end, timelineEventsLimit] =
      getCurrentEndStartInterval(selectedTimePeriod);

    getParameters?.({ end, start, timelineEventsLimit });
  }, [customTimePeriod.start, customTimePeriod.end, selectedTimePeriod]);

  useEffect(() => {
    if (!errorTimePeriod) {
      return;
    }
    getIsError?.(errorTimePeriod);
  }, [errorTimePeriod]);
};

export default useAwesomeTimePeriod;
