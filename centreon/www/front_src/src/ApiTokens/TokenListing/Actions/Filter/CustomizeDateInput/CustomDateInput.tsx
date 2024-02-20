import dayjs from 'dayjs';
import { PrimitiveAtom, useAtom } from 'jotai';

import { DateTimePickerInput } from '@centreon/ui';

import ActionList from '../../../../TokenCreation/CustomTimePeriod/ActionsList';
import InvisibleField from '../../../../TokenCreation/CustomTimePeriod/InvisibleField';
import { useStyles } from '../filter.styles';

interface Props {
  anchorEl: HTMLDivElement | null;
  onClose: () => void;
  storageData: PrimitiveAtom<Date | null>;
}

const CustomDateInput = ({
  anchorEl,
  onClose,
  storageData
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const defaultDate = dayjs().toDate();

  const [currentDate, setCurrentDate] = useAtom(storageData);

  const changeDate = ({ date }): void => {
    setCurrentDate(dayjs(date).toDate());
  };

  const acceptDate = (): void => {
    if (currentDate) {
      onClose();

      return;
    }
    setCurrentDate(defaultDate);
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
      date={currentDate ?? defaultDate}
      open={Boolean(anchorEl)}
      slotProps={slotProps}
      slots={slots}
      timeSteps={{ minutes: 1 }}
    />
  );
};

export default CustomDateInput;
