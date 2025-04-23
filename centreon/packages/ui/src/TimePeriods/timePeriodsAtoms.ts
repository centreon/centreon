import dayjs from 'dayjs';
import { atom } from 'jotai';
import { T, always, cond, gte, isNil } from 'ramda';

import {
  defaultTimePeriod,
  getTimePeriodById,
  getTimePeriodFromNow
} from './helpers';
import { CustomTimePeriod, TimePeriod, TimePeriodById } from './models';

export const selectedTimePeriodAtom = atom<TimePeriod | null>(
  defaultTimePeriod
);

const defaultCustomTimePeriod = getTimePeriodFromNow(defaultTimePeriod);

export const customTimePeriodAtom = atom(defaultCustomTimePeriod);

export const errorTimePeriodAtom = atom(false);

export const getNewCustomTimePeriod = ({
  start,
  end
}: Omit<CustomTimePeriod, 'timelineEventsLimit'>): CustomTimePeriod => {
  const customTimePeriodInDay = dayjs
    .duration(dayjs(end).diff(dayjs(start)))
    .asDays();

  const timelineEventsLimit = cond<number, number>([
    [gte(1), always(20)],
    [gte(7), always(100)],
    [T, always(500)]
  ])(customTimePeriodInDay) as number;

  return {
    end,
    start,
    timelineEventsLimit
  };
};

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

    const newCustomTimePeriod = getNewCustomTimePeriod({
      ...customTimePeriod,
      [property]: date
    });

    set(customTimePeriodAtom, newCustomTimePeriod);
    set(selectedTimePeriodAtom, null);
  }
);

export const getDatesDerivedAtom = atom(
  (get) =>
    (timePeriod?: TimePeriod | null): [string, string, number] => {
      const customTimePeriod = get(customTimePeriodAtom);

      if (isNil(timePeriod)) {
        return [
          customTimePeriod.start.toISOString(),
          customTimePeriod.end.toISOString(),
          customTimePeriod.timelineEventsLimit
        ];
      }

      return [
        timePeriod.getStart().toISOString(),
        new Date(Date.now()).toISOString(),
        timePeriod.timelineEventsLimit
      ];
    }
);

export const adjustTimePeriodDerivedAtom = atom(
  null,
  (
    _,
    set,
    adjustTimePeriodProps: Omit<CustomTimePeriod, 'timelineEventsLimit'>
  ) => {
    set(customTimePeriodAtom, getNewCustomTimePeriod(adjustTimePeriodProps));
    set(selectedTimePeriodAtom, null);
  }
);
