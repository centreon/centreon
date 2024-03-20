import { SetStateAction, memo, useState } from 'react';

import dayjs from 'dayjs';

import { DateTimePickerInput } from '@centreon/ui';

import { useStyles } from '../filter.styles';

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
  const { classes } = useStyles();
  const { setDate } = dataDate;
  const defaultDate = dayjs().toDate();

  const [error, setError] = useState('');
  const [customizedDate, setCustomizedDate] = useState<Date>(defaultDate);

  const changeDate = ({ date: time }): void => {
    setError('');
    setCustomizedDate(dayjs(time).toDate());
  };

  const hideCalendar = (): void => {
    setDisplayCalendar(false);
  };

  const handleDate = (callback?: () => void): void => {
    if (!dayjs(customizedDate).isValid()) {
      setError('invalid date');

      return;
    }
    setError('');
    setDate(customizedDate);
    callback?.();
  };

  const onKeyDown = (event): void => {
    if (event.key !== 'Enter') {
      // todo
      handleDate();

      return;
    }

    handleDate(hideCalendar);
  };

  const close = (): void => {
    handleDate(hideCalendar);
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
    <>
      <DateTimePickerInput
        changeDate={changeDate}
        closeOnSelect={false}
        date={customizedDate}
        slotProps={slotProps}
        timeSteps={{ minutes: 1 }}
        onClose={close}
      />
      <HelperText error={error} />
    </>
  );
};

export default memo(DateInput);
