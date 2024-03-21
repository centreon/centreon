import { SetStateAction, memo, useState } from 'react';

import dayjs from 'dayjs';

import { Typography } from '@mui/material';

import { DateTimePickerInput } from '@centreon/ui';

import { useStyles } from '../../../../TokenCreation/InputCalendar/inputCalendar.styles';

import HelperText from './HelperText';

type SetAtom<Args extends Array<unknown>, Result> = (...args: Args) => Result;
interface DataDate {
  date: Date | null;
  setDate: SetAtom<[SetStateAction<Date | null>], void>;
}
interface Props {
  dataDate: DataDate;
  setDisplayCalendar;
}

const DateInput = ({ dataDate, setDisplayCalendar }: Props): JSX.Element => {
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
      onKeyDown
    }
  };

  return (
    <div className={classes.container}>
      <div className={classes.containerDatePicker}>
        <div className={classes.secondaryContainer}>
          <Typography variant="overline"> Until </Typography>
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
        <HelperText error={error} />
      </div>
    </div>
  );
};

export default memo(DateInput);
