import dayjs from 'dayjs';

import { DateTimePicker } from '@mui/x-date-pickers';

import { useDateTimePickerAdapter } from '@centreon/ui';

import { CustomTimePeriodProperty } from './models';

interface Props {
  changeDate: (props) => void;
  date: Date | dayjs.Dayjs | null;
  disabled?: boolean;
  maxDate?: Date | dayjs.Dayjs;
  minDate?: Date | dayjs.Dayjs;
  property: CustomTimePeriodProperty;
}

const DateTimePickerInput = ({
  date,
  maxDate,
  minDate,
  property,
  changeDate,
  disabled = false
}: Props): JSX.Element => {
  const { desktopPickerMediaQuery } = useDateTimePickerAdapter();

  const changeTime = (newValue: dayjs.Dayjs | null): void => {
    changeDate({ date: dayjs(newValue).toDate(), property });
  };

  return (
    <DateTimePicker<dayjs.Dayjs>
      dayOfWeekFormatter={(day: string): string =>
        day.substring(0, 2).toUpperCase()
      }
      desktopModeMediaQuery={desktopPickerMediaQuery}
      disabled={disabled}
      maxDate={maxDate && dayjs(maxDate)}
      minDate={minDate && dayjs(minDate)}
      value={dayjs(date)}
      onChange={changeTime}
    />
  );
};

export default DateTimePickerInput;
