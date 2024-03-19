import { Dispatch, SetStateAction, useState } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { FormHelperText } from '@mui/material';

import { DateTimePickerInput, useLocaleDateTimeFormat } from '@centreon/ui';

import { CreateTokenFormValues } from '../../TokenListing/models';
import { isInvalidDate } from '../utils';
import { labelInvalidDateCreationToken } from '../../translatedLabels';

import { useStyles } from './customTimePeriod.styles';

interface Props {
  setIsDisplayingDateTimePicker: Dispatch<SetStateAction<boolean>>;
  windowHeight: number;
}

const CustomTimePeriod = ({
  setIsDisplayingDateTimePicker,
  windowHeight
}: Props): JSX.Element => {
  const { classes } = useStyles({ windowHeight });
  const { t } = useTranslation();

  const { format } = useLocaleDateTimeFormat();

  const { setFieldValue, values, setFieldError } =
    useFormikContext<CreateTokenFormValues>();

  const { customizeDate } = values;

  const minDate = dayjs().add(1, 'd').toDate();
  const minDateTime = dayjs(minDate).endOf('m').toDate();

  const [open, setOpen] = useState(false);
  const [error, setError] = useState('');
  const [endDate, setEndDate] = useState<Date>(customizeDate ?? minDateTime);

  const handleCustomizeDate = (): void => {
    if (isInvalidDate({ endTime: endDate })) {
      // setFieldError('duration', t(labelInvalidDateCreationToken));
      setError(t(labelInvalidDateCreationToken));
      setOpen(false);

      return;
    }
    setError('');
    setFieldValue('customizeDate', endDate);
    setFieldValue('duration', {
      id: 'customize',
      name: format({ date: endDate, formatString: 'LLL' })
    });

    setOpen(false);
    setIsDisplayingDateTimePicker(false);
  };

  const onOpen = (): void => {
    setOpen(true);
  };

  const changeDate = ({ date }): void => {
    const currentDate = dayjs(date).toDate();
    setEndDate(currentDate);
  };

  return (
    <div style={{ marginBottom: 16 }}>
      <div
        style={{
          alignItems: 'center',
          display: 'flex',
          flexDirection: 'row'
        }}
      >
        <div style={{ flex: 0.1 }}>Until</div>
        <DateTimePickerInput
          reduceAnimations
          changeDate={changeDate}
          className={classes.dateTimePicker}
          closeOnSelect={false}
          date={endDate}
          minDate={minDate}
          minDateTime={minDateTime}
          open={open}
          timeSteps={{ minutes: 1 }}
          onClose={handleCustomizeDate}
          onOpen={onOpen}
        />
      </div>
      {error && (
        <FormHelperText error style={{ textAlign: 'center' }}>
          {error}
        </FormHelperText>
      )}
    </div>
  );
};

export default CustomTimePeriod;
