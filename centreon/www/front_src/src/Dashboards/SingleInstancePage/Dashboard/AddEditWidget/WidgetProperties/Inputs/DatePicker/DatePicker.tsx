import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import dayjs from 'dayjs';

import { Typography } from '@mui/material';

import { DateTimePickerInput } from '@centreon/ui';

import { WidgetPropertyProps } from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import Subtitle from '../../../../components/Subtitle';

import { useDatePicker } from './useDatePicker';

const DatePicker = ({
  propertyName,
  label,
  isInGroup,
  datePicker
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { canEditField } = useCanEditProperties();

  const { currentDate, changeDate, maxDate, locale, timezone } = useDatePicker({
    datePicker,
    propertyName
  });

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div>
      {label && <Label>{t(label)}</Label>}
      <DateTimePickerInput
        changeDate={changeDate}
        date={currentDate.toDate()}
        disabled={!canEditField}
        locale={locale}
        maxDate={maxDate}
        minDate={dayjs().tz(timezone)}
        property=""
        timezone={timezone}
      />
    </div>
  );
};

export default DatePicker;
