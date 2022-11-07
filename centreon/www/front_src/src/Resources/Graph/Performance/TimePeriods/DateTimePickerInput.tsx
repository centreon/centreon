<<<<<<< HEAD
import { Dispatch, SetStateAction, useState } from 'react';

import dayjs from 'dayjs';

import { DateTimePicker } from '@mui/lab';
import { TextFieldProps } from '@mui/material';
=======
import * as React from 'react';

import { DateTimePicker } from '@material-ui/pickers';
>>>>>>> centreon/dev-21.10.x

import { TextField } from '@centreon/ui';

import { CustomTimePeriodProperty } from '../../../Details/tabs/Graph/models';
<<<<<<< HEAD
import useDateTimePickerAdapter from '../../../useDateTimePickerAdapter';

interface Props {
  changeDate: (props) => void;
=======

const DateTimeTextField = React.forwardRef(
  (props, ref: React.ForwardedRef<HTMLDivElement>): JSX.Element => (
    <TextField {...props} ref={ref} size="small" />
  ),
);

interface Props {
  changeDate: (props) => () => void;
  commonPickersProps;
>>>>>>> centreon/dev-21.10.x
  date: Date;
  maxDate?: Date;
  minDate?: Date;
  property: CustomTimePeriodProperty;
<<<<<<< HEAD
  setDate: Dispatch<SetStateAction<Date>>;
}

const renderDateTimePickerTextField =
  (blur: () => void) =>
  ({ inputRef, inputProps, InputProps }: TextFieldProps): JSX.Element => {
    return (
      <TextField
        // eslint-disable-next-line react/no-unstable-nested-components
        EndAdornment={(): JSX.Element => <div>{InputProps?.endAdornment}</div>}
        inputProps={{
          ...inputProps,
          ref: inputRef,
          style: { padding: 8 },
        }}
        onBlur={blur}
      />
    );
  };

const DateTimePickerInput = ({
  date,
  maxDate,
  minDate,
  property,
  changeDate,
  setDate,
}: Props): JSX.Element => {
  const [isOpen, setIsOpen] = useState(false);
  const { getDestinationAndConfiguredTimezoneOffset, formatKeyboardValue } =
    useDateTimePickerAdapter();

  const changeTime = (
    newValue: dayjs.Dayjs | null,
    keyBoardValue: string | undefined,
  ): void => {
    if (isOpen) {
      changeDate({ date: dayjs(newValue).toDate(), property });

      return;
    }
    const value = dayjs(formatKeyboardValue(keyBoardValue))
      .add(
        dayjs.duration({ hours: getDestinationAndConfiguredTimezoneOffset() }),
      )
      .toDate();

    setDate(value);
  };

  const blur = (): void => {
    changeDate({ date, property });
  };

  return (
    <DateTimePicker<dayjs.Dayjs>
      hideTabs
      PopperProps={{
        open: isOpen,
      }}
      maxDate={maxDate && dayjs(maxDate)}
      minDate={minDate && dayjs(minDate)}
      open={isOpen}
      renderInput={renderDateTimePickerTextField(blur)}
      value={date}
      onChange={changeTime}
      onClose={(): void => setIsOpen(false)}
      onOpen={(): void => setIsOpen(true)}
=======
  setDate: React.Dispatch<React.SetStateAction<Date>>;
}

const DateTimePickerInput = ({
  commonPickersProps,
  date,
  minDate,
  maxDate,
  property,
  setDate,
  changeDate,
}: Props): JSX.Element => {
  const inputProp = {
    TextFieldComponent: DateTimeTextField,
  };

  return (
    <DateTimePicker
      {...commonPickersProps}
      {...inputProp}
      hideTabs
      inputVariant="filled"
      maxDate={maxDate}
      minDate={minDate}
      size="small"
      value={date}
      variant="inline"
      onChange={(value): void => setDate(new Date(value?.toDate() || 0))}
      onClose={changeDate({
        date,
        property,
      })}
>>>>>>> centreon/dev-21.10.x
    />
  );
};

export default DateTimePickerInput;
