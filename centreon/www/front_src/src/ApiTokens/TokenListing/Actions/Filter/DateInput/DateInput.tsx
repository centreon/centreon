import { SetStateAction, memo, useState } from 'react';

import dayjs from 'dayjs';

import { Typography } from '@mui/material';

import { DateTimePickerInput } from '@centreon/ui';

import { useStyles } from '../../../../TokenCreation/InputCalendar/inputCalendar.styles';
import { labelUntil } from '../../../../translatedLabels';

import HelperText from './HelperText';

type SetAtom<Args extends Array<unknown>, Result> = (...args: Args) => Result;
interface DataDate {
  date: Date | null;
  setDate: SetAtom<[SetStateAction<Date | null>], void>;
}
interface Props {
  dataDate: DataDate;
  label: string;
  setDisplayCalendar;
}

const DateInput = ({
  dataDate,
  setDisplayCalendar,
  label
}: Props): JSX.Element => {
  const { classes } = useStyles({});
  const { date, setDate } = dataDate;
  const defaultDate = dayjs().toDate();

  const [error, setError] = useState('');
  const [customizedDate, setCustomizedDate] = useState<Date>(
    date ?? defaultDate
  );

  const changeDate = ({ date: time }): void => {
    setCustomizedDate(dayjs(time).toDate());
  };

  const insertDate = (): void => {
    setDate(customizedDate);
    setDisplayCalendar(false);
  };

  const handleDate = (callback?: () => void): void => {
    if (!dayjs(customizedDate).isValid()) {
      setError('invalid date');

      return;
    }
    setError('');
    callback?.();
  };

  const onKeyDown = (event): void => {
    if (event.key !== 'Enter') {
      handleDate();

      return;
    }

    handleDate(insertDate);
  };

  const close = (): void => {
    handleDate(insertDate);
  };

  const slotProps = {
    popper: {
      className: classes.popper
    },
    textField: {
      inputProps: {
        'data-testid': 'calendarInput'
      },
      onKeyDown
    }
  };

  return (
    <div
      className={classes.container}
      data-testid={`${label}-calendarContainer`}
    >
      <div className={classes.containerDatePicker}>
        <div className={classes.secondaryContainer}>
          <Typography variant="overline"> {labelUntil} </Typography>
        </div>
        <DateTimePickerInput
          changeDate={changeDate}
          className={classes.picker}
          closeOnSelect={false}
          date={customizedDate}
          slotProps={slotProps}
          timeSteps={{ minutes: 1 }}
          onClose={close}
        />
      </div>
      <HelperText error={error} />
    </div>
  );
};

export default memo(DateInput);
