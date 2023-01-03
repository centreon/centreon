import { Dispatch, SetStateAction, useState } from 'react';

import dayjs from 'dayjs';

import { DesktopDateTimePicker } from '@mui/x-date-pickers';
import { TextFieldProps } from '@mui/material';

import { TextField } from '@centreon/ui';

import { CustomTimePeriodProperty } from '../../../Details/tabs/Graph/models';
import useDateTimePickerAdapter from '../../../useDateTimePickerAdapter';

interface Props {
  changeDate: (props) => void;
  date: Date;
  maxDate?: Date;
  minDate?: Date;
  property: CustomTimePeriodProperty;
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
          style: { padding: 8 }
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
  setDate
}: Props): JSX.Element => {
  const [isOpen, setIsOpen] = useState(false);
  const { getDestinationAndConfiguredTimezoneOffset, formatKeyboardValue } =
    useDateTimePickerAdapter();

  const changeTime = (
    newValue: dayjs.Dayjs | null,
    keyBoardValue: string | undefined
  ): void => {
    if (isOpen) {
      changeDate({ date: dayjs(newValue).toDate(), property });

      return;
    }
    const value = dayjs(formatKeyboardValue(keyBoardValue))
      .add(
        dayjs.duration({ hours: getDestinationAndConfiguredTimezoneOffset() })
      )
      .toDate();

    setDate(value);
  };

  const blur = (): void => {
    changeDate({ date, property });
  };

  return (
    <DesktopDateTimePicker<dayjs.Dayjs>
      hideTabs
      PopperProps={{
        open: isOpen
      }}
      dayOfWeekFormatter={(day): string => day.substring(0, 2).toUpperCase()}
      maxDate={maxDate && dayjs(maxDate)}
      minDate={minDate && dayjs(minDate)}
      open={isOpen}
      renderInput={renderDateTimePickerTextField(blur)}
      showToolbar={false}
      value={date}
      onChange={changeTime}
      onClose={(): void => setIsOpen(false)}
      onOpen={(): void => setIsOpen(true)}
    />
  );
};

export default DateTimePickerInput;
