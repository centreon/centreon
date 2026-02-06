import dayjs from 'dayjs';
import { always, cond, gte, T } from 'ramda';

import { getTimePeriodFromNow } from './helpers';
import type { TimePeriod } from './models';

const useSortTimePeriods = (
  timePeriods: Array<TimePeriod>
): Array<TimePeriod> => {
  const adjustedTimePeriods = timePeriods.map((item) => {
    const { end, start } = getTimePeriodFromNow(item);

    const numberOfDays = dayjs.duration(dayjs(end).diff(dayjs(start))).asDays();

    const timelineEventsLimit = cond<number, number>([
      [gte(1), always(20)],
      [gte(7), always(100)],
      [T, always(500)]
    ])(numberOfDays) as number;

    return { item: { ...item, timelineEventsLimit }, numberOfDays };
  });

  const sortedTimePeriods = adjustedTimePeriods.sort(
    (a, b) => a.numberOfDays - b.numberOfDays
  );

  return sortedTimePeriods.map((element) => element.item);
};

export default useSortTimePeriods;
