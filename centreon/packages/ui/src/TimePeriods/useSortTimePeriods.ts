import dayjs from 'dayjs';

import { getTimePeriodFromNow } from './helpers';
import { TimePeriod } from './models';

const useSortTimePeriods = (
  timePeriods: Array<TimePeriod>
): Array<TimePeriod> => {
  const adjustedTimePeriods = timePeriods.map((item) => {
    const { end, start } = getTimePeriodFromNow(item);

    const numberOfDays = dayjs.duration(dayjs(end).diff(dayjs(start))).asDays();

    return { item, numberOfDays };
  });

  const sortedTimePeriods = adjustedTimePeriods.sort(
    (a, b) => a.numberOfDays - b.numberOfDays
  );

  return sortedTimePeriods.map((element) => element.item);
};

export default useSortTimePeriods;
