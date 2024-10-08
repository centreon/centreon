import { useMemo } from 'react';

import dayjs from 'dayjs';
import { FormikValues, useFormikContext } from 'formik';
import { isNil, lte, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { FormHelperText, FormLabel, useTheme } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import StrengthProgress from '../StrengthProgress';
import TimeInputs from '../TimeInputs';
import { TimeInputConfiguration } from '../models';
import {
  goodBlockingDuration,
  sevenDays,
  strongBlockingDuration,
  weakBlockingDuration
} from '../timestamps';
import {
  labelGood,
  labelStrong,
  labelThisWillNotBeUsedBecauseNumberOfAttemptsIsNotDefined,
  labelTimeThatMustPassBeforeNewConnection,
  labelWeak
} from '../translatedLabels';

import { attemptsFieldName } from './Attempts';
import { getField } from './utils';

const blockingDurationFieldName = 'blockingDuration';

const useStyles = makeStyles()({
  passwordBlockingDuration: {
    maxWidth: 'fit-content'
  }
});

const BlockingDuration = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const { values, setFieldValue, errors } = useFormikContext<FormikValues>();

  const change = (value: number): void => {
    setFieldValue(blockingDurationFieldName, value || null);
  };

  const blockingDurationValue = getField<number>({
    field: blockingDurationFieldName,
    object: values
  });

  const blockingDurationError = getField<string>({
    field: blockingDurationFieldName,
    object: errors
  });

  const attemptsValue = getField<number>({
    field: attemptsFieldName,
    object: values
  });

  const thresholds = useMemo(
    () => [
      {
        color: theme.palette.error.main,
        label: labelWeak,
        value: weakBlockingDuration
      },
      {
        color: theme.palette.warning.main,
        label: labelGood,
        value: goodBlockingDuration
      },
      {
        color: theme.palette.success.main,
        label: labelStrong,
        value: strongBlockingDuration
      }
    ],
    []
  );

  const areAttemptsEmpty = isNil(attemptsValue);

  const displayStrengthProgress = useMemo(
    () =>
      isNil(blockingDurationError) &&
      not(isNil(blockingDurationValue)) &&
      not(areAttemptsEmpty),
    [blockingDurationError, blockingDurationValue, areAttemptsEmpty]
  );

  const maxHoursAndMinutesOption = useMemo(
    (): number | undefined =>
      lte(dayjs.duration({ days: 7 }).asMilliseconds(), blockingDurationValue)
        ? 0
        : undefined,
    [blockingDurationValue]
  );

  const timeInputConfigurations: Array<TimeInputConfiguration> = [
    { dataTestId: 'local_blockingDurationDays', maxOption: 7, unit: 'days' },
    {
      dataTestId: 'local_blockingDurationHours',
      maxOption: maxHoursAndMinutesOption,
      unit: 'hours'
    },
    {
      dataTestId: 'local_blockingDurationMinutes',
      maxOption: maxHoursAndMinutesOption,
      unit: 'minutes'
    }
  ];

  return useMemoComponent({
    Component: (
      <div className={classes.passwordBlockingDuration}>
        <FormLabel>{t(labelTimeThatMustPassBeforeNewConnection)}</FormLabel>
        <TimeInputs
          baseName={blockingDurationFieldName}
          inputLabel={labelTimeThatMustPassBeforeNewConnection}
          maxDuration={sevenDays}
          timeInputConfigurations={timeInputConfigurations}
          timeValue={blockingDurationValue}
          onChange={change}
        />
        {blockingDurationError && (
          <FormHelperText error>{blockingDurationError}</FormHelperText>
        )}
        {areAttemptsEmpty && (
          <FormHelperText error>
            {t(labelThisWillNotBeUsedBecauseNumberOfAttemptsIsNotDefined)}
          </FormHelperText>
        )}
        {displayStrengthProgress && (
          <StrengthProgress
            max={sevenDays}
            thresholds={thresholds}
            value={blockingDurationValue || 0}
          />
        )}
      </div>
    ),
    memoProps: [blockingDurationValue, blockingDurationError, attemptsValue]
  });
};

export default BlockingDuration;
