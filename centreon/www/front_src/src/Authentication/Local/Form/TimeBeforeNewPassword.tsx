import { FormHelperText, FormLabel } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import dayjs from 'dayjs';
import { FormikValues, useFormikContext } from 'formik';
import { lte } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { TimeInputConfiguration } from '../models';
import TimeInputs from '../TimeInputs';
import { sevenDays } from '../timestamps';
import { labelMinimumTimeBetweenPasswordChanges } from '../translatedLabels';
import { getField } from './utils';

const delayBeforeNewPasswordFieldName = 'delayBeforeNewPassword';

const TimeBeforeNewPassword = (): JSX.Element => {
  const { t } = useTranslation();

  const { values, setFieldValue, errors } = useFormikContext<FormikValues>();

  const change = (value: number): void => {
    setFieldValue(delayBeforeNewPasswordFieldName, value || null);
  };

  const delayBeforeNewPasswordValue = getField<number>({
    field: delayBeforeNewPasswordFieldName,
    object: values
  });

  const delayBeforeNewPasswordError = getField<string>({
    field: delayBeforeNewPasswordFieldName,
    object: errors
  });

  const maxHoursOption = useMemo(
    (): number | undefined =>
      lte(
        dayjs.duration({ days: 7 }).asMilliseconds(),
        delayBeforeNewPasswordValue
      )
        ? 0
        : undefined,
    [delayBeforeNewPasswordValue]
  );

  const timeInputConfigurations: Array<TimeInputConfiguration> = [
    {
      dataTestId: 'local_timeBetweenPasswordChangesDays',
      maxOption: 7,
      unit: 'days'
    },
    {
      dataTestId: 'local_timeBetweenPasswordChangesHours',
      maxOption: maxHoursOption,
      unit: 'hours'
    }
  ];

  return useMemoComponent({
    Component: (
      <div>
        <FormLabel>{t(labelMinimumTimeBetweenPasswordChanges)}</FormLabel>
        <TimeInputs
          baseName={delayBeforeNewPasswordFieldName}
          inputLabel={labelMinimumTimeBetweenPasswordChanges}
          maxDuration={sevenDays}
          onChange={change}
          timeInputConfigurations={timeInputConfigurations}
          timeValue={delayBeforeNewPasswordValue}
        />
        {delayBeforeNewPasswordError && (
          <FormHelperText error>{delayBeforeNewPasswordError}</FormHelperText>
        )}
      </div>
    ),
    memoProps: [delayBeforeNewPasswordValue, delayBeforeNewPasswordError]
  });
};

export default TimeBeforeNewPassword;
