import { Dispatch, SetStateAction } from 'react';

import dayjs from 'dayjs';

export const dateFormat = 'L';
export const timeFormat = 'LT';
export const dateTimeFormat = `${dateFormat} ${timeFormat}`;

export enum CustomTimePeriodProperty {
  end = 'end',
  start = 'start'
}

export interface TimePeriod {
  dateTimeFormat: string;
  getStart: () => Date;
  id: string;
  largeName: string;
  name: string;
  timelineEventsLimit: number;
}

export const label1Day = '1 day';
export const label7Days = '7 days';
export const label31Days = '31 days';
export const labelLastDay = 'Last day';
export const labelLast7Days = 'Last 7 days';
export const labelLast31Days = 'Last 31 days';

export const lastDayPeriod: TimePeriod = {
  dateTimeFormat: timeFormat,
  getStart: (): Date => dayjs(Date.now()).subtract(24, 'hour').toDate(),
  id: 'last_24_h',
  largeName: labelLastDay,
  name: label1Day,
  timelineEventsLimit: 20
};

export const last7Days: TimePeriod = {
  dateTimeFormat: dateFormat,
  getStart: (): Date => dayjs(Date.now()).subtract(7, 'day').toDate(),
  id: 'last_7_days',
  largeName: labelLast7Days,
  name: label7Days,
  timelineEventsLimit: 100
};

export const last31Days: TimePeriod = {
  dateTimeFormat: dateFormat,
  getStart: (): Date => dayjs(Date.now()).subtract(31, 'day').toDate(),
  id: 'last_31_days',
  largeName: labelLast31Days,
  name: label31Days,
  timelineEventsLimit: 500
};

export const timePeriods: Array<TimePeriod> = [
  lastDayPeriod,
  last7Days,
  last31Days
];

export interface TimePeriodById {
  id: string;
  timePeriods: Array<TimePeriod>;
}

export interface CustomTimePeriod {
  end: Date;
  start: Date;
}

export interface DateTimePickerInputModel {
  changeDate: (props) => void;
  date: Date | dayjs.Dayjs | null;
  disabled?: boolean;
  maxDate?: Date | dayjs.Dayjs;
  minDate?: Date | dayjs.Dayjs;
  onClosePicker?: (isClosed: boolean) => void;
  property: CustomTimePeriodProperty;
  setDate: Dispatch<SetStateAction<Date | null>>;
}

export interface GetNewCustomTimePeriodProps {
  end: Date;
  start: Date;
}

export interface TimeLineAxisTickFormat extends CustomTimePeriod {
  timeLineLimit: number;
  xAxisTickFormat: string;
}
