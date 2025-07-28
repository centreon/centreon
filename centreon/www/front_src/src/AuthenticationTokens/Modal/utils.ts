import dayjs from 'dayjs';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';
import relativeTime from 'dayjs/plugin/relativeTime';
import { equals } from 'ramda';

import { SelectEntry } from '@centreon/ui';

import { TokenType } from '../models';
import { Duration, UnitDate } from './models';

dayjs.extend(isSameOrBefore);
dayjs.extend(relativeTime);

export const maxDays = 90;

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

export const tokenTypes = [
  { id: TokenType.API, name: 'API' },
  { id: TokenType.CMA, name: 'Centreon monitoring agent' }
];

export const dataDuration: Array<Duration> = [
  { id: '7days', name: '7 days', unit: UnitDate.Day, value: 7 },
  { id: '30days', name: '30 days', unit: UnitDate.Day, value: 30 },
  { id: '60days', name: '60 days', unit: UnitDate.Day, value: 60 },
  { id: '90days', name: '90 days', unit: UnitDate.Day, value: 90 },
  { id: '1year', name: '1 year', unit: UnitDate.Year, value: 1 },
  { id: 'neverExpire', name: 'Never expire', unit: UnitDate.Year, value: null },
  { id: 'customize', name: 'Customize', unit: null, value: null }
];
