import dayjs from 'dayjs';
import { find, propEq } from 'ramda';

import { PopoverOrigin, PopoverPosition } from '@mui/material';

import { timeFormat, dateFormat } from '@centreon/ui';

import {
  label1Day,
  label31Days,
  label7Days,
  labelLast31Days,
  labelLast7Days,
  labelLastDay
} from './labels';

export enum OriginHorizontalEnum {
  center = 'center',
  left = 'left',
  right = 'right'
}

export enum OriginVerticalEnum {
  bottom = 'bottom',
  center = 'center',
  top = 'top'
}

export enum anchorReferenceEnum {
  anchorEl = 'anchorEl',
  anchorPosition = 'anchorPosition',
  none = 'none'
}

export interface AcceptDateProps {
  date: Date;
  property: CustomTimePeriodProperty;
}

export interface CustomStyle {
  classNameError?: string;
  classNamePaper?: string;
  classNamePicker?: string;
}

export interface PopoverData {
  anchorEl?: Element;
  anchorOrigin?: PopoverOrigin;
  anchorPosition?: PopoverPosition;
  anchorReference?: 'anchorEl' | 'anchorPosition' | 'none';
  onClose?: () => void;
  open: boolean;
  transformOrigin?: PopoverOrigin;
}

export interface PickersData {
  acceptDate: (props: AcceptDateProps) => void;
  customTimePeriod: CustomTimePeriod;
  disabledPickerEndInput?: boolean;
  disabledPickerStartInput?: boolean;
  getIsErrorDatePicker?: (value: boolean) => void;
  maxDatePickerEndInput?: Date | dayjs.Dayjs;
  maxDatePickerStartInput?: Date;
  minDatePickerEndInput?: Date;
  minDatePickerStartInput?: Date;
  onCloseEndPicker?: (isClosed: boolean) => void;
  onCloseStartPicker?: (isClosed: boolean) => void;
}

export type TimePeriodId = 'last_24_h' | 'last_7_days' | 'last_31_days';

export interface TimePeriod {
  dateTimeFormat: string;
  getStart: () => Date;
  id: TimePeriodId;
  largeName: string;
  name: string;
  timelineEventsLimit: number;
}

export interface CustomTimePeriod {
  end: Date;
  start: Date;
  timelineLimit?: number;
  xAxisTickFormat?: string;
}

export interface StoredCustomTimePeriod {
  end: string;
  start: string;
}

export enum CustomTimePeriodProperty {
  end = 'end',
  start = 'start'
}

export interface ChangeCustomTimePeriodProps {
  date: Date;
  property: CustomTimePeriodProperty;
}

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

export const getTimePeriodById = (id: TimePeriodId): TimePeriod =>
  find<TimePeriod>(propEq('id', id))(timePeriods) as TimePeriod;

export interface AdjustTimePeriodProps {
  end: Date;
  start: Date;
}

export enum LabelDays {
  last_24_h = 'last_24_h',
  last_31_days = 'last_31_days',
  last_7_days = 'last_7_days'
}

export type LabelDay = {
  [key in LabelDays]?: { largeName: string; name: string };
};

export interface LabelTimePeriodPicker {
  labelEnd: string;
  labelFrom: string;
}
