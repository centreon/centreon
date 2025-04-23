import dayjs from 'dayjs';
import { find, propEq } from 'ramda';

import {
  CustomTimePeriod,
  TimePeriod,
  TimePeriodById,
  lastDayPeriod
} from '../models';

export const defaultTimePeriod = lastDayPeriod;

export const getTimePeriodFromNow = (
  timePeriod: TimePeriod | null
): CustomTimePeriod => {
  return {
    end: new Date(Date.now()),
    start: new Date(timePeriod?.getStart() || 0),
    timelineEventsLimit: timePeriod?.timelineEventsLimit as number
  };
};

export const getTimePeriodById = ({
  id,
  timePeriods
}: TimePeriodById): TimePeriod =>
  find<TimePeriod>(propEq(id, 'id'))(timePeriods) as TimePeriod;

export const isInvalidDate = ({ startDate, endDate }): boolean =>
  dayjs(startDate).isSameOrAfter(dayjs(endDate), 'minute');
