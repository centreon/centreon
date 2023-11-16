import { useCallback } from 'react';

import dayjs, { Dayjs } from 'dayjs';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { DateTimePicker, LocalizationProvider } from '@mui/x-date-pickers';
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
  maxDate?: Date;
  minDate?: Date;
  property: CustomTimePeriodProperty | string;
}

const DateTimePickerInput = ({
  date,
  maxDate,
  minDate,
  property,
  changeDate,
  disabled = false,
  desktopMediaQuery
}: Props): JSX.Element => {
  const desktopPickerMediaQuery =
    '@media (min-width: 1024px) or (pointer: fine)';

  const { timezone, locale } = useAtomValue(userAtom);

  const isUTC = equals(timezone, 'UTC');

  const changeTime = (newValue: dayjs.Dayjs | null): void => {
    changeDate({ date: dayjs(newValue).toDate(), property });
  };

  const formatDate = useCallback(
    (currentDate: Date | null): Dayjs => {
      return isUTC ? dayjs.utc(currentDate) : dayjs.tz(currentDate, timezone);
    },
    [isUTC, timezone]
  );

  return (
    <LocalizationProvider
      adapterLocale={locale.substring(0, 2)}
      dateAdapter={AdapterDayjs}
      dateLibInstance={dayjs}
    >
      <DateTimePicker<dayjs.Dayjs>
        dayOfWeekFormatter={(dayOfWeek) =>
          dayOfWeek.substring(0, 2).toLocaleUpperCase()
        }
        desktopModeMediaQuery={desktopMediaQuery ?? desktopPickerMediaQuery}
        disabled={disabled}
        maxDate={maxDate && formatDate(maxDate)}
        minDate={minDate && formatDate(minDate)}
        value={formatDate(date)}
        onChange={changeTime}
      />
    </LocalizationProvider>
  );
};

export default DateTimePickerInput;
