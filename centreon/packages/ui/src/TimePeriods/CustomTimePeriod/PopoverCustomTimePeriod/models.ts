import { PopoverOrigin, PopoverPosition } from '@mui/material';

import { CustomTimePeriod, CustomTimePeriodProperty } from '../../models';

import { PickersStartEndDateModel } from './usePickersStartEndDate';

interface RangeDate {
  max?: Date;
  min?: Date;
}

export interface AcceptDateProps {
  date: Date;
  property: CustomTimePeriodProperty;
}

export interface PickersData {
  acceptDate: (props: AcceptDateProps) => void;
  customTimePeriod: CustomTimePeriod;
  getError?: (value: boolean) => void;
  isDisabledEndPicker?: boolean;
  isDisabledStartPicker?: boolean;
  rangeEndDate?: RangeDate;
  rangeStartDate?: RangeDate;
}

export interface PopoverData {
  anchorEl?: HTMLButtonElement;
  anchorOrigin?: PopoverOrigin;
  anchorPosition?: PopoverPosition;
  onClose?: () => void;
  open: boolean;
  transformOrigin?: PopoverOrigin;
}

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

export const defaultAnchorOrigin = {
  horizontal: OriginHorizontalEnum.center,
  vertical: OriginVerticalEnum.top
};

export const defaultTransformOrigin = {
  horizontal: OriginHorizontalEnum.center,
  vertical: OriginVerticalEnum.top
};

export enum PickersStartEndDateDirection {
  column = 'column',
  row = 'row'
}

interface DisabledPicker {
  isDisabledEndPicker?: boolean;
  isDisabledStartPicker?: boolean;
}
type PickersDate = Pick<PickersData, 'rangeEndDate' | 'rangeStartDate'>;

export interface PickersStartEndDateProps
  extends PickersDate,
    PickersStartEndDateModel {
  direction?: PickersStartEndDateDirection;
  disabled?: DisabledPicker;
}
