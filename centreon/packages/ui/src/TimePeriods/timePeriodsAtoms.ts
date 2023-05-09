import { atom } from 'jotai';
import { always, cond, gte, isNil, not, pipe, T } from 'ramda';
import dayjs from 'dayjs';

import {
  defaultTimePeriod,
  getTimePeriodById,
  getTimePeriodFromNow
} from './helpers';
import {
  CustomTimePeriod,
  GraphQueryParametersProps,
  TimePeriod,
  TimePeriodById
} from './models';

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

// export const graphQueryParametersDerivedAtom = atom(
//   (get) =>
//     ({ timePeriod, startDate, endDate }: GraphQueryParametersProps): string => {
//       const getDates = get(getDatesDerivedAtom);

//       if (pipe(isNil, not)(timePeriod)) {
//         const [start, end] = getDates(timePeriod);

//         return `?start=${start}&end=${end}`;
//       }

//       return `?start=${startDate?.toISOString()}&end=${endDate?.toISOString()}`;
//     }
// );

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
