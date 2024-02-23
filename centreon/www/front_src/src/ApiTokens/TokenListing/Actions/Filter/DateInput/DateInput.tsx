import { useState } from 'react';

import dayjs from 'dayjs';

import { DateTimePickerInput } from '@centreon/ui';

import { useStyles } from '../filter.styles';
import { Property } from '../models';

import CustomField from './CustomField';

interface Props {
  dataDate;
  label: string;
  property: Property;
}

const DateInput = ({ dataDate, label, property }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { date, setDate } = dataDate;
  const defaultDate = dayjs().toDate();
  const [open, setOpen] = useState(false);

  const [error, setError] = useState(false);
  const [customizedDate, setCustomizedDate] = useState<Date | null>(null);

  const changeDate = ({ date: time }): void => {
    if (!dayjs(time).isValid()) {
      setError(true);

      return;
    }
    setError(false);
    setCustomizedDate(dayjs(time).toDate());
  };

  const onClear = (): void => {
    setCustomizedDate(null);
    setDate(null);
    setOpen(false);
    setError(false);
  };

  const onClose = (): void => {
    setOpen(false);
  };

  const getIsDisplayingCalendar = (value): void => {
    setOpen(value);
  };

  const onAccept = (): void => {
    if (!dayjs(customizedDate).isValid()) {
      setError(true);

      return;
    }
    setDate(customizedDate);
    setError(false);
    setOpen(false);
  };

  const slotProps = {
    actionBar: {
      actions: ['clear', 'accept'],
      onAccept,
      onClear
    },
    field: {
      className: classes.field,
      customizedDate,
      dataDate,
      error,
      getIsDisplayingCalendar,
      label,
      onClear,
      property
    },
    popper: {
      className: classes.popper,
      placement: 'bottom'
    }
  };

  const slots = {
    field: CustomField
  };

  return (
    <div className={classes.containerDate}>
      <DateTimePickerInput
        changeDate={changeDate}
        closeOnSelect={false}
        date={customizedDate || date || defaultDate}
        open={open}
        slotProps={slotProps}
        slots={slots}
        onClose={onClose}
      />
    </div>
  );
};

export default DateInput;
