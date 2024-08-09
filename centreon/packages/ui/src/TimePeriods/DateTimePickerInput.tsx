import { useCallback } from 'react';

import dayjs, { Dayjs } from 'dayjs';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import {
  DateTimePicker,
  DateTimePickerProps,
  LocalizationProvider
} from '@mui/x-date-pickers';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';

import { userAtom } from '@centreon/ui-context';

import { CustomTimePeriodProperty } from './models';

interface ChangeDateProps {
  date: Date;
  property: CustomTimePeriodProperty | string;
}

interface Props {
  changeDate: (props: ChangeDateProps) => void;
  date: Date | null;
  desktopMediaQuery?: string;
  disabled?: boolean;
  locale?: string;
  maxDate?: Date;
  minDate?: Date;
  minDateTime?: Date;
  property: CustomTimePeriodProperty | string;
  timezone?: string;
}

const DateTimePickerInput = ({
  date,
  maxDate,
  minDate,
  minDateTime,
  property,
  changeDate,
  disabled = false,
  desktopMediaQuery,
  locale,
  timezone,
  ...rest
}: Props & DateTimePickerProps<dayjs.Dayjs>): JSX.Element => {
  const desktopPickerMediaQuery =
    '@media (min-width: 1024px) or (pointer: fine)';

  const user = useAtomValue(userAtom);

  const isUTC = equals(timezone ?? user.timezone, 'UTC');

  const changeTime = (newValue: dayjs.Dayjs | null): void => {
    changeDate({ date: dayjs(newValue).toDate(), property });
  };

  const formatDate = useCallback(
    (currentDate: Date | null): Dayjs => {
      if (timezone) {
        return dayjs(currentDate).tz(timezone);
      }

      return isUTC
        ? dayjs.utc(currentDate)
        : dayjs.tz(currentDate, user.timezone);
    },
    [isUTC, timezone, user.timezone]
  );

  return (
    <LocalizationProvider
      adapterLocale={(locale ?? user.locale).substring(0, 2)}
      dateAdapter={AdapterDayjs}
      dateLibInstance={dayjs}
    >
      <DateTimePicker<dayjs.Dayjs>
        dayOfWeekFormatter={(dayOfWeek) =>
          dayOfWeek.format('dd').toLocaleUpperCase()
        }
        desktopModeMediaQuery={desktopMediaQuery ?? desktopPickerMediaQuery}
        disabled={disabled}
        maxDate={maxDate && formatDate(maxDate)}
        minDate={minDate && formatDate(minDate)}
        minDateTime={minDateTime && formatDate(minDateTime)}
        value={formatDate(date)}
        onChange={changeTime}
        {...rest}
      />
    </LocalizationProvider>
  );
};

export default DateTimePickerInput;
