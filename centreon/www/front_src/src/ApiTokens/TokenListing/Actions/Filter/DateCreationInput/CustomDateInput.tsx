import { useState } from 'react';

import dayjs from 'dayjs';

import { DateTimePickerInput } from '@centreon/ui';

import ActionList from '../../../../TokenCreation/CustomTimePeriod/ActionsList';
import InvisibleField from '../../../../TokenCreation/CustomTimePeriod/InvisibleField';
import { useStyles } from '../filter.styles';

interface Props {
  anchorEl: HTMLDivElement | null;
  getCurrentDate: (date: Date | null) => void;
  onClose: () => void;
  selectedDate: Date;
}

const CustomDateInput = ({
  anchorEl,
  onClose,
  getCurrentDate,
  selectedDate
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const defaultDate = dayjs().toDate();

  const [currentDate, setCurrentDate] = useState<Date>(defaultDate);

  const changeDate = ({ date }): void => {
    getCurrentDate?.(dayjs(date).toDate());
    setCurrentDate(dayjs(date).toDate());
  };

  const acceptDate = (): void => {
    getCurrentDate(currentDate);
    onClose();
  };

  const slotProps = {
    actionBar: {
      acceptDate,
      cancelDate: onClose
    },
    popper: {
      anchorEl,
      className: classes.popper,
      placement: 'bottom'
    }
  };

  const slots = {
    actionBar: ActionList,
    field: InvisibleField
  };

  return (
    <DateTimePickerInput
      changeDate={changeDate}
      date={selectedDate ?? currentDate}
      open={Boolean(anchorEl)}
      slotProps={slotProps}
      slots={slots}
      timeSteps={{ minutes: 1 }}
    />
  );
};

export default CustomDateInput;
