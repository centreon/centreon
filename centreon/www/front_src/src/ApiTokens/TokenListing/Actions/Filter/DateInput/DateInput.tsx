import { useState } from 'react';

import dayjs from 'dayjs';
import { PrimitiveAtom, useAtom } from 'jotai';

import { DateTimePickerInput } from '@centreon/ui';

import { useStyles } from '../filter.styles';

interface Props {
  label: string;
  storageData: PrimitiveAtom<Date | null>;
}

const DateInput = ({ storageData, label }: Props): JSX.Element => {
  const { classes } = useStyles();
  const [open, setOpen] = useState(false);

  const [currentDate, setCurrentDate] = useAtom(storageData);

  const changeDate = ({ date }): void => {
    if (!dayjs(date).isValid) {
      return;
    }
    setCurrentDate(dayjs(date).toDate());
  };

  const onClear = (): void => {
    setCurrentDate(null);
    setOpen(false);
  };
  const onOpen = (): void => {
    setOpen(true);
  };
  const onClose = (): void => {
    setOpen(false);
  };

  const slotProps = {
    actionBar: {
      actions: ['clear', 'accept'],
      onClear
    },
    popper: {
      className: classes.popper,
      placement: 'bottom'
    },
    textField: {
      className: classes.field,
      error: false,
      label
    }
  };

  return (
    <div className={classes.input}>
      <DateTimePickerInput
        changeDate={changeDate}
        closeOnSelect={false}
        date={currentDate}
        open={open}
        slotProps={slotProps}
        onClose={onClose}
        onOpen={onOpen}
      />
    </div>
  );
};

export default DateInput;
