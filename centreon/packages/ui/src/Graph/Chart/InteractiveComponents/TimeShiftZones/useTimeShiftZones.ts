import { useEffect, useState } from 'react';

import { add, equals, negate, prop } from 'ramda';

import { GraphInterval, GraphIntervalProperty, Interval } from '../../models';

import { GetShiftDate, TimeShiftDirection } from './models';

interface Props {
  direction: TimeShiftDirection;
  graphInterval: GraphInterval;
}

export const useTimeShiftZones = ({
  graphInterval,
  direction
}: Props): Interval => {
  const shiftRatio = 2;

  const [start, setStart] = useState<Date>();
  const [end, setEnd] = useState<Date>();

  const getShiftedDate = ({
    property,
    timeShiftDirection,
    timePeriod
  }: GetShiftDate): Date | null => {
    if (!timePeriod?.end || !timePeriod?.start) {
      return null;
    }
    const adjustTimePeriodProps =
      (new Date(timePeriod.end).getTime() -
        new Date(timePeriod.start).getTime()) /
      shiftRatio;

    const date = prop(property, timePeriod);

    if (!date) {
      return null;
    }

    return new Date(
      add(
        new Date(date).getTime(),
        equals(timeShiftDirection, TimeShiftDirection.backward)
          ? negate(adjustTimePeriodProps)
          : adjustTimePeriodProps
      )
    );
  };

  useEffect(() => {
    const endInterval = getShiftedDate({
      property: GraphIntervalProperty.end,
      timePeriod: graphInterval,
      timeShiftDirection: direction
    });

    const startInterval = getShiftedDate({
      property: GraphIntervalProperty.start,
      timePeriod: graphInterval,
      timeShiftDirection: direction
    });

    if (!endInterval || !startInterval) {
      return;
    }
    setStart(startInterval);
    setEnd(endInterval);
  }, [graphInterval.end, graphInterval.start, direction]);

  return { end, start } as Interval;
};
