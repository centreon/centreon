import { atom } from 'jotai';
import { isNil, not, pipe } from 'ramda';

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

export const getDatesDerivedAtom = atom(
  (get) =>
    (timePeriod?: TimePeriod | null): [string, string] => {
      const customTimePeriod = get(customTimePeriodAtom);

      if (isNil(timePeriod)) {
        return [
          customTimePeriod.start.toISOString(),
          customTimePeriod.end.toISOString()
        ];
      }

      return [
        timePeriod.getStart().toISOString(),
        new Date(Date.now()).toISOString()
      ];
    }
);

export const graphQueryParametersDerivedAtom = atom(
  (get) =>
    ({ timePeriod, startDate, endDate }: GraphQueryParametersProps): string => {
      const getDates = get(getDatesDerivedAtom);

      if (pipe(isNil, not)(timePeriod)) {
        const [start, end] = getDates(timePeriod);

        return `?start=${start}&end=${end}`;
      }

      return `?start=${startDate?.toISOString()}&end=${endDate?.toISOString()}`;
    }
);

export const adjustTimePeriodDerivedAtom = atom(
  null,
  (_, set, adjustTimePeriodProps: CustomTimePeriod) => {
    set(customTimePeriodAtom, adjustTimePeriodProps);
    set(selectedTimePeriodAtom, null);
  }
);

// a deplacer le timelinelimit vers le graph

// export const getNewTimeLineLimitAndAxisTickFormat = ({
//   start,
//   end
// }: GetNewCustomTimePeriodProps): TimeLineAxisTickFormat => {
//   const customTimePeriodInDay = dayjs
//     .duration(dayjs(end).diff(dayjs(start)))
//     .asDays();

//   const xAxisTickFormat = gte(customTimePeriodInDay, 2)
//     ? dateFormat
//     : timeFormat;

//   const timelineLimit = cond<number, number>([
//     [gte(1), always(20)],
//     [gte(7), always(100)],
//     [T, always(500)]
//   ])(customTimePeriodInDay);

//   return {
//     end,
//     start,
//     timelineLimit,
//     xAxisTickFormat
//   };
// };
