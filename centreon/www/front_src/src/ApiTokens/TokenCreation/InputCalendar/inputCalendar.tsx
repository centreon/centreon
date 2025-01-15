import { Dispatch, SetStateAction, useState } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { DateTimePickerInput, useLocaleDateTimeFormat } from '@centreon/ui';

import HelperText from '../../TokenListing/Actions/Filter/DateInput/HelperText';
import { CreateTokenFormValues } from '../../TokenListing/models';
import { labelInvalidDateCreationToken } from '../../translatedLabels';
import { isInvalidDate } from '../utils';

import { useStyles } from './inputCalendar.styles';

interface Props {
  setIsDisplayingDateTimePicker: Dispatch<SetStateAction<boolean>>;
  windowHeight: number;
}

const InputCalendar = ({
  setIsDisplayingDateTimePicker,
  windowHeight
}: Props): JSX.Element => {
  const { classes } = useStyles({ windowHeight });
  const { t } = useTranslation();

  const { format } = useLocaleDateTimeFormat();

  const { setFieldValue, values } = useFormikContext<CreateTokenFormValues>();

  const { customizeDate } = values;

  const minDate = dayjs().add(1, 'd').toDate();
  const minDateTime = dayjs(minDate).endOf('m').toDate();

  const [open, setOpen] = useState(false);
  const [error, setError] = useState('');
  const [endDate, setEndDate] = useState<Date>(customizeDate ?? minDateTime);

  const insertDate = (): void => {
    setFieldValue('customizeDate', endDate);
    setFieldValue('duration', {
      id: 'customize',
      name: format({ date: endDate, formatString: 'LLL' })
    });
    setIsDisplayingDateTimePicker(false);
  };

  const handleCustomizeDate = (callback?: () => void): void => {
    if (isInvalidDate({ endTime: endDate })) {
      setError(t(labelInvalidDateCreationToken));
      setOpen(false);

      return;
    }
    setError('');

    setOpen(false);
    callback?.();
  };

  const close = (): void => {
    handleCustomizeDate(insertDate);
  };

  const onOpen = (): void => {
    setOpen(true);
  };

  const changeDate = ({ date }): void => {
    setError('');
    const currentDate = dayjs(date).toDate();
    setEndDate(currentDate);
  };

  const onKeyDown = (event): void => {
    if (event.key !== 'Enter') {
      handleCustomizeDate();

      return;
    }
    handleCustomizeDate(insertDate);
  };

  const slotProps = {
    desktopPaper: {
      classes: { root: classes.root }
    },
    popper: {
      className: classes.popper
    },
    textField: {
      inputProps: {
        'data-testid': 'calendarInput'
      },
      onKeyDown
    }
  };

  return (
    <div className={classes.container}>
      <div className={classes.containerDatePicker}>
        <div className={classes.subContainer}>
          <Typography variant="overline"> Until </Typography>
        </div>
        <DateTimePickerInput
          reduceAnimations
          changeDate={changeDate}
          className={classes.dateTimePicker}
          closeOnSelect={false}
          date={endDate}
          minDate={minDate}
          minDateTime={minDateTime}
          open={open}
          slotProps={slotProps}
          timeSteps={{ minutes: 1 }}
          onClose={close}
          onOpen={onOpen}
        />
      </div>
      <HelperText className={classes.error} error={error} />
    </div>
  );
};

export default InputCalendar;
