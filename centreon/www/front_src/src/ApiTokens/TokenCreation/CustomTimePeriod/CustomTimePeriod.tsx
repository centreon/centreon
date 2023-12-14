import { Dispatch, SetStateAction, useMemo, useState } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { DateTimePickerInput, useLocaleDateTimeFormat } from '@centreon/ui';

import { CreateTokenFormValues } from '../../TokenListing/models';
import { labelInvalidDateCreationToken } from '../../translatedLabels';
import { AnchorElDuration, OpenPicker } from '../models';
import { isInvalidDate as validateDate } from '../utils';

import ActionList from './ActionsList';
import InvisibleField from './InvisibleField';

interface Props {
  anchorElDuration: AnchorElDuration;
  openPicker: OpenPicker;
  setIsDisplayingDateTimePicker: Dispatch<SetStateAction<boolean>>;
}

const CustomTimePeriod = ({
  anchorElDuration,
  openPicker,
  setIsDisplayingDateTimePicker
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { format } = useLocaleDateTimeFormat();

  const { setFieldValue, values, setFieldError } =
    useFormikContext<CreateTokenFormValues>();

  const { customizeDate } = values;

  const { open, setOpen } = openPicker;
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
    setOpen(false);
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

  const isInvalidDate = useMemo(() => {
    return validateDate({ endTime: endDate });
  }, [endDate]);

  const slotProps = {
    actionBar: { acceptDate, cancelDate, isInvalidDate },
    popper: { anchorEl }
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
      open={open}
      slotProps={slotProps}
      slots={slots}
      timeSteps={{ minutes: 1 }}
    />
  );
};

export default CustomTimePeriod;
