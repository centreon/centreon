import { SetStateAction, useState, Dispatch, useMemo } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { ClickAwayListener, Box, Popper } from '@mui/material';

import { DateTimePickerInput, useLocaleDateTimeFormat } from '@centreon/ui';

import { CreateTokenFormValues } from '../../TokenListing/models';
import { AnchorElDuration, OpenPicker } from '../models';
import { isInvalidDate as validateDate } from '../utils';
import { labelInvalidDateCreationToken } from '../../translatedLabels';

import ActionList from './ActionsList';

const ButtonField = (): JSX.Element => {
  return <div style={{ opacity: 0 }}>Customize</div>;
};

const PopperTest = (props) => {
  console.log('---->>>>props', props);

  const handleClickAway = () => {
    console.log('click awaaaaaay');
    props.setIsClickedOutside(true);
  };

  return (
    <ClickAwayListener onClickAway={handleClickAway}>
      <Popper
        anchorEl={props.anchorEl}
        // className={props.className}
        open={props?.open}
        placement={props.placement}
        sx={{ zIndex: 1300 }}
        // role="option"
      >
        {props?.children?.({
          TransitionProps: { in: true },
          placement: 'bottom'
        })}
      </Popper>
    </ClickAwayListener>
  );
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
  const { t } = useTranslation();
  const { setFieldValue, values, setFieldError } =
    useFormikContext<CreateTokenFormValues>();
  const { format } = useLocaleDateTimeFormat();

  const [isClickedOutside, setIsClickedOutside] = useState(false);

  const { customizeDate } = values;

  const { open, setOpen } = openPicker;
  const { anchorEl, setAnchorEl } = anchorElDuration;

  const minDate = dayjs().add(1, 'd').toDate();
  const minDateTime = dayjs(minDate).endOf('m').toDate();

  const [endDate, setEndDate] = useState<Date>(customizeDate ?? minDate);

  // const slots = { field: ButtonField };

  const changeDate = ({ date }): void => {
    const currentDate = dayjs(date).toDate();
    setEndDate(currentDate);
    console.log('------>onchange', validateDate({ endTime: currentDate }));

    // if (validateDate({ endTime: currentDate })) {
    //   setFieldError('duration', t(labelInvalidDateCreationToken));
    // }
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

  const onClose = (): void => {
    console.log('close');
    initialize();
  };
  console.log('----->open', open);

  const cancel = () => {
    initialize();
  };

  const accept = (): void => {
    if (validateDate({ endTime: endDate })) {
      setFieldError('duration', t(labelInvalidDateCreationToken));
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
    actionBar: { accept, cancel, isInvalidDate },
    popper: { anchorEl, open, setIsClickedOutside, setOpen }
  };

  const slots = {
    actionBar: ActionList,
    field: ButtonField,
    popper: PopperTest
  };

  console.log({ isClickedOutside });

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
      // onClose={isClickedOutside ? undefined : onClose}
    />
  );
};

export default CustomTimePeriod;
