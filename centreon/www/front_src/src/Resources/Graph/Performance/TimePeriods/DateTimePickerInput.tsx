import { Dispatch, SetStateAction, useCallback, useState } from 'react';

import dayjs, { Dayjs } from 'dayjs';
import { equals } from 'ramda';
import { useAtomValue } from 'jotai';

import { DateTimePicker } from '@mui/x-date-pickers/DateTimePicker';
import { TextFieldProps } from '@mui/material';

import { TextField } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

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
  const { timezone } = useAtomValue(userAtom);

  const isUTC = equals(timezone, 'UTC');
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

  const formatDate = useCallback(
    (currentDate: Date | null): Dayjs => {
      return isUTC ? dayjs.utc(currentDate) : dayjs.tz(currentDate, timezone);
    },
    [isUTC, timezone],
  );

  return (
    <DateTimePicker<dayjs.Dayjs>
      hideTabs
      PopperProps={{
        open: isOpen,
      }}
      dayOfWeekFormatter={(day): string =>
        day.substring(0, 2).toLocaleUpperCase()
      }
      maxDate={maxDate && formatDate(maxDate)}
      minDate={minDate && formatDate(minDate)}
      open={isOpen}
      renderInput={renderDateTimePickerTextField(blur)}
      showToolbar={false}
      value={formatDate(date)}
      onChange={changeTime}
      onClose={(): void => setIsOpen(false)}
      onOpen={(): void => setIsOpen(true)}
    />
  );
};

export default DateTimePickerInput;
