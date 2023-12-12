import { SetStateAction, useState, Dispatch } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';

import { DateTimePickerInput, useLocaleDateTimeFormat } from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';

import { AnchorElDuration, OpenPicker } from './models';

const ButtonField = (): JSX.Element => {
  return <div style={{ opacity: 0 }}>Customize</div>;
};

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
  const { setFieldValue, values } = useFormikContext<CreateTokenFormValues>();
  const { format } = useLocaleDateTimeFormat();

  const { customizeDate } = values;

  const { open, setOpen } = openPicker;
  const { anchorEl, setAnchorEl } = anchorElDuration;

  const minDate = dayjs().add(1, 'd').toDate();
  const minDateTime = dayjs(minDate).add(5, 'm').startOf('s').toDate();

  const [endDate, setEndDate] = useState<Date>(customizeDate ?? minDate);

  const slotProps = {
    actionBar: { actions: ['accept'], style: { padding: 0 } },
    popper: { anchorEl }
  };

  const slots = { field: ButtonField };

  const changeDate = ({ date }): void => {
    const currentDate = dayjs(date).toDate();
    setEndDate(currentDate);
  };

  const initialize = (): void => {
    setOpen(false);
    setAnchorEl(null);
    setIsDisplayingDateTimePicker(false);
  };

  const onClose = (): void => {
    setFieldValue('duration', {
      id: 'customize',
      name: format({ date: endDate, formatString: 'LLL' })
    });
    setFieldValue('customizeDate', endDate);
    initialize();
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
      propsPickerEnd={{ open, slotProps, slots }}
      slotProps={slotProps}
      slots={slots}
      onClose={onClose}
    />
  );
};

export default CustomTimePeriod;
