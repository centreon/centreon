import dayjs from 'dayjs';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';

dayjs.extend(isSameOrBefore);

export const minimumLifeSpanToken = { unit: 'day', value: 1 };

export const isInvalidDate = ({ startTime = new Date(), endTime }): boolean => {
  return (
    dayjs(endTime).diff(dayjs(startTime), minimumLifeSpanToken.unit) <
    minimumLifeSpanToken.value
  );
};
