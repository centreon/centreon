import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';

import { DateTimePicker, LocalizationProvider } from '@mui/x-date-pickers';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';

import { userAtom } from '@centreon/ui-context';

import { CustomTimePeriodProperty } from './models';

interface Props {
  changeDate: (props) => void;
  date: Date | null;
  desktopMediaQuery?: string;
  disabled?: boolean;
  maxDate?: Date;
  minDate?: Date;
  property: CustomTimePeriodProperty;
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

  const changeTime = (newValue: dayjs.Dayjs | null): void => {
    changeDate({ date: dayjs(newValue).toDate(), property });
  };

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
        maxDate={maxDate && dayjs.tz(maxDate, timezone)}
        minDate={minDate && dayjs.tz(minDate, timezone)}
        value={dayjs.tz(date, timezone)}
        onChange={changeTime}
      />
    </LocalizationProvider>
  );
};

export default DateTimePickerInput;
