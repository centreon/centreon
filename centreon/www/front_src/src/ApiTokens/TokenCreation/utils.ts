import dayjs from 'dayjs';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';
import relativeTime from 'dayjs/plugin/relativeTime';
import { equals } from 'ramda';

import { SelectEntry } from '@centreon/ui';

import { UnitDate, maxDays } from './models';

dayjs.extend(isSameOrBefore);
dayjs.extend(relativeTime);

export const minimumLifeSpanToken = { unit: 'day', value: 1 };

export const isInvalidDate = ({ startTime = new Date(), endTime }): boolean => {
  return (
    dayjs(endTime).diff(dayjs(startTime), minimumLifeSpanToken.unit) <
      minimumLifeSpanToken.value || !dayjs(endTime).isValid()
  );
};

export const formatLabelDuration = (label: string): string => {
  return label
    .split(' ')
    .map((item) => (equals(item, 'a') ? 1 : item))
    .join(' ');
};

export const getDuration = ({
  startTime,
  endTime,
  isCustomizeDate
}): SelectEntry => {
  const endDate = dayjs(endTime);
  const startDate = dayjs(startTime);

  if (isCustomizeDate) {
    const name = formatLabelDuration(endDate.to(startDate, true));

    return { id: 'customize', name };
  }

  const numberOfDays = Math.round(endDate.diff(startDate, UnitDate.Day, true));

  if (numberOfDays <= maxDays) {
    const durationName = `${numberOfDays} days`;

    return {
      id: durationName.trim(),
      name: durationName
    };
  }
  const name = formatLabelDuration(endDate.to(startDate, true));

  return { id: name.trim(), name };
};
