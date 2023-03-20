import dayjs from 'dayjs';

import { PopoverOrigin, PopoverPosition } from '@mui/material';

import {
  CustomTimePeriod,
  CustomTimePeriodProperty
} from '../../../Details/tabs/Graph/models';

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
