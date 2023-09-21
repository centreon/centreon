import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { TextField } from '../../..';

import {
  labelGlobalRefreshInterval,
  labelInterval,
  labelSeconds
} from './translatedLabels';
import { useGlobalRefreshIntervalStyles } from './DashboardForm.styles';

const GlobalRefreshFieldOption = (): JSX.Element => {
  const { classes } = useGlobalRefreshIntervalStyles();
  const { t } = useTranslation();

  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const value = values.globalRefreshInterval.interval;

  const changeInput = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(
      'globalRefreshInterval.interval',
      event.target.value ? Number(event.target.value) : null
    );
  };

  return (
    <div className={classes.globalRefreshInterval}>
      <Typography>{t(labelGlobalRefreshInterval)}</Typography>
      <TextField
        autoSize
        dataTestId={labelInterval}
        inputProps={{
          'aria-label': t(labelInterval),
          min: 1
        }}
        size="compact"
        type="number"
        value={value || undefined}
        onChange={changeInput}
      />
      <Typography>{t(labelSeconds, { count: value })}</Typography>
    </div>
  );
};

export default GlobalRefreshFieldOption;
