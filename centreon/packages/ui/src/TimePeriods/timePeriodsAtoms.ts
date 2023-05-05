import dayjs from 'dayjs';
import { atom } from 'jotai';
import { T, always, cond, gte } from 'ramda';

import {
  defaultTimePeriod,
  getTimePeriodById,
  getTimePeriodFromNow
} from './helpers';
import {
  GetNewCustomTimePeriodProps,
  TimeLineAxisTickFormat,
  TimePeriod,
  TimePeriodById,
  dateFormat,
  timeFormat
} from './models';

export const selectedTimePeriodAtom = atom<TimePeriod | null>(
  defaultTimePeriod
);

const defaultCustomTimePeriod = getTimePeriodFromNow(defaultTimePeriod);

export const customTimePeriodAtom = atom(defaultCustomTimePeriod);

export const changeSelectedTimePeriodDerivedAtom = atom(
  null,
  (_, set, { id, timePeriods }: TimePeriodById) => {
    const timePeriod = getTimePeriodById({ id, timePeriods });

    set(selectedTimePeriodAtom, timePeriod);

    const newCustomTimePeriod = getTimePeriodFromNow(timePeriod);

    set(customTimePeriodAtom, newCustomTimePeriod);
  }
);

export const changeCustomTimePeriodDerivedAtom = atom(
  null,
  (get, set, { date, property }) => {
    const customTimePeriod = get(customTimePeriodAtom);

    const newCustomTimePeriod = {
      ...customTimePeriod,
      [property]: date
    };

    set(customTimePeriodAtom, newCustomTimePeriod);
    set(selectedTimePeriodAtom, null);
  }
);

export const getNewTimeLineLimitAndAxisTickFormat = ({
  start,
  end
}: GetNewCustomTimePeriodProps): TimeLineAxisTickFormat => {
  const customTimePeriodInDay = dayjs
    .duration(dayjs(end).diff(dayjs(start)))
    .asDays();

  const xAxisTickFormat = gte(customTimePeriodInDay, 2)
    ? dateFormat
    : timeFormat;

  const timelineLimit = cond<number, number>([
    [gte(1), always(20)],
    [gte(7), always(100)],
    [T, always(500)]
  ])(customTimePeriodInDay);

  return {
    end,
    start,
    timelineLimit,
    xAxisTickFormat
  };
};
