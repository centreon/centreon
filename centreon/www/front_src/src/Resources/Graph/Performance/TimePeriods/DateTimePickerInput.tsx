import { Dispatch, SetStateAction, useState } from 'react';

import dayjs from 'dayjs';

import { DesktopDateTimePicker } from '@mui/x-date-pickers';
import { TextFieldProps } from '@mui/material';

import { TextField } from '@centreon/ui';

import { CustomTimePeriodProperty } from '../../../Details/tabs/Graph/models';
import useDateTimePickerAdapter from '../../../useDateTimePickerAdapter';

interface Props {
  changeDate: (props) => void;
  date: Date | dayjs.Dayjs | null;
  disabled?: boolean;
  maxDate?: Date | dayjs.Dayjs;
  minDate?: Date | dayjs.Dayjs;
  onClosePicker?: (isClosed: boolean) => void;
  property: CustomTimePeriodProperty;
  setDate: Dispatch<SetStateAction<Date | null>>;
}

const renderDateTimePickerTextField =
  (blur: () => void) =>
  ({ inputRef, inputProps, InputProps }: TextFieldProps): JSX.Element => {
    return (
      <TextField
        // eslint-disable-next-line react/no-unstable-nested-components
        EndAdornment={(): JSX.Element => <div>{InputProps?.endAdornment}</div>}
        dataTestId="calendar"
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
  setDate,
  onClosePicker,
  disabled = false
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
  const close = (): void => {
    setIsOpen(false);
    onClosePicker?.(true);
  };

  const open = (): void => {
    setIsOpen(true);
    onClosePicker?.(false);
  };

  return (
    <DesktopDateTimePicker<dayjs.Dayjs>
      hideTabs
      PopperProps={{
        open: isOpen
      }}
      dayOfWeekFormatter={(day: string): string =>
        day.substring(0, 2).toUpperCase()
      }
      disabled={disabled}
      maxDate={maxDate && dayjs(maxDate)}
      minDate={minDate && dayjs(minDate)}
      open={isOpen}
      renderInput={renderDateTimePickerTextField(blur)}
      value={date as dayjs.Dayjs}
      onChange={changeTime}
      onClose={close}
      onOpen={open}
    />
  );
};

export default DateTimePickerInput;
