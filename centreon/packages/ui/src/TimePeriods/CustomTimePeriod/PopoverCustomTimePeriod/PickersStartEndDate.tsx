import { useAtomValue } from 'jotai';

import { Typography } from '@mui/material';
import { LocalizationProvider } from '@mui/x-date-pickers';

import { userAtom } from '@centreon/ui-context';

import DateTimePickerInput from '../../DateTimePickerInput';
import {
  CustomTimePeriodProperty,
  DateTimePickerInputModel
} from '../../models';
import useDateTimePickerAdapter from '../../useDateTimePickerAdapter';

import ErrorText from './ErrorText';
import { PickersData } from './models';
import { PickersStartEndDateModel } from './usePickersStartEndDate';

interface PropsPickersDateWithLabel extends DateTimePickerInputModel {
  label: string;
}

const PickerDateWithLabel = ({
  label,
  changeDate,
  maxDate,
  minDate,
  property,
  onClosePicker,
  disabled,
  date,
  setDate
}: PropsPickersDateWithLabel): JSX.Element => {
  return (
    <>
      <Typography>{label}</Typography>
      <div aria-label={label}>
        <DateTimePickerInput
          changeDate={changeDate}
          date={date}
          disabled={disabled}
          maxDate={maxDate}
          minDate={minDate}
          property={property}
          setDate={setDate}
          onClosePicker={onClosePicker}
        />
      </div>
    </>
  );
};

interface DisabledPicker {
  isDisabledEndPicker?: boolean;
  isDisabledStartPicker?: boolean;
}
type PickersDate = Pick<PickersData, 'rangeEndDate' | 'rangeStartDate'>;

interface Props extends PickersDate, PickersStartEndDateModel {
  disabled?: DisabledPicker;
}

const PickersStartEndDate = ({
  startDate,
  endDate,
  error,
  disabled,
  changeDate,
  rangeStartDate,
  rangeEndDate
}: Props): JSX.Element => {
  const { locale } = useAtomValue(userAtom);
  const { Adapter } = useDateTimePickerAdapter();

  const { start, setStart } = startDate;
  const { end, setEnd } = endDate;

  const maxStart = rangeStartDate?.max;
  const minStart = rangeStartDate?.min;
  const maxEnd = rangeEndDate?.max;
  const minEnd = rangeEndDate?.min;

  return (
    <LocalizationProvider
      data-testid="popover"
      dateAdapter={Adapter}
      locale={locale.substring(0, 2)}
    >
      <PickerDateWithLabel
        changeDate={changeDate}
        date={start}
        disabled={disabled?.isDisabledStartPicker}
        label="start"
        maxDate={maxStart}
        minDate={minStart}
        property={CustomTimePeriodProperty.start}
        setDate={setStart}
      />
      <PickerDateWithLabel
        changeDate={changeDate}
        date={end}
        disabled={disabled?.isDisabledEndPicker}
        label="end"
        maxDate={maxEnd}
        minDate={minEnd}
        property={CustomTimePeriodProperty.end}
        setDate={setEnd}
      />

      {error && (
        <ErrorText message="The end date must be greater than the start date" />
      )}
    </LocalizationProvider>
  );
};

export default PickersStartEndDate;
