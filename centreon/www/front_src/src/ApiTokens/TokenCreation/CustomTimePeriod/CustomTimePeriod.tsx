import { Dispatch, SetStateAction, useState } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { DateTimePickerInput, useLocaleDateTimeFormat } from '@centreon/ui';

import { CreateTokenFormValues } from '../../TokenListing/models';
import { labelInvalidDateCreationToken } from '../../translatedLabels';
import { AnchorElDuration } from '../models';
import { isInvalidDate as validateDate } from '../utils';

import ActionList from './ActionsList';
import InvisibleField from './InvisibleField';
import { useStyles } from './customTimePeriod.styles';

interface Props {
  anchorElDuration: AnchorElDuration;
  setIsDisplayingDateTimePicker: Dispatch<SetStateAction<boolean>>;
  windowHeight: number;
}

const CustomTimePeriod = ({
  anchorElDuration,
  setIsDisplayingDateTimePicker,
  windowHeight
}: Props): JSX.Element => {
  const { classes } = useStyles({ windowHeight });
  const { t } = useTranslation();

  const { format } = useLocaleDateTimeFormat();

  const { setFieldValue, values, setFieldError } =
    useFormikContext<CreateTokenFormValues>();

  const { customizeDate } = values;

  const { anchorEl, setAnchorEl } = anchorElDuration;

  const minDate = dayjs().add(1, 'd').toDate();
  const minDateTime = dayjs(minDate).endOf('m').toDate();

  const [endDate, setEndDate] = useState<Date>(customizeDate ?? minDate);

  const changeDate = ({ date }): void => {
    const currentDate = dayjs(date).toDate();
    setEndDate(currentDate);

    setFieldValue('duration', {
      id: 'customize',
      name: format({ date: currentDate, formatString: 'LLL' })
    });
  };

  const initialize = (): void => {
    setAnchorEl(null);
    setIsDisplayingDateTimePicker(false);
  };

  const cancelDate = (): void => {
    initialize();
  };

  const acceptDate = (): void => {
    if (validateDate({ endTime: endDate })) {
      setFieldError('duration', {
        invalidDate: t(labelInvalidDateCreationToken)
      });
      initialize();

      return;
    }
    setFieldValue('customizeDate', endDate);

    initialize();
  };

  const slotProps = {
    actionBar: {
      acceptDate,
      cancelDate,
      isInvalidDate: validateDate({ endTime: endDate })
    },
    desktopPaper: {
      classes: { root: classes.root }
    },
    popper: {
      anchorEl,
      className: classes.popper
    }
  };

  const slots = {
    actionBar: ActionList,
    field: InvisibleField
  };

  return (
    <DateTimePickerInput
      reduceAnimations
      changeDate={changeDate}
      closeOnSelect={false}
      date={endDate}
      minDate={minDate}
      minDateTime={minDateTime}
      open={Boolean(anchorEl)}
      slotProps={slotProps}
      slots={slots}
      timeSteps={{ minutes: 1 }}
    />
  );
};

export default CustomTimePeriod;
