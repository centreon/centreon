import { Typography } from '@mui/material';

import { refreshIntervalAtom } from '@centreon/ui-context';

import { type FormikValues, useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import pluralize from 'pluralize';
import { isEmpty } from 'ramda';
import type { ChangeEvent } from 'react';
import { useTranslation } from 'react-i18next';

import { TextField } from '../../..';
import { useGlobalRefreshIntervalStyles } from './DashboardForm.styles';
import {
  labelGlobalRefreshInterval,
  labelInterval,
  labelSeconds
} from './translatedLabels';

const GlobalRefreshFieldOption = (): JSX.Element => {
  const { classes } = useGlobalRefreshIntervalStyles();
  const { t } = useTranslation();

  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const platformRefreshInterval = useAtomValue(refreshIntervalAtom);

  const value = values.refresh?.interval;

  const changeInput = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(
      'refresh.interval',
      !isEmpty(event.target.value) ? Number(event.target.value) || 1 : null
    );
  };

  return (
    <div className={classes.globalRefreshInterval}>
      <Typography>{t(labelGlobalRefreshInterval)}</Typography>
      <TextField
        autoSize
        dataTestId={labelInterval}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'aria-label': t(labelInterval) as string,
              min: 1
            }
          }
        }}
        size="compact"
        type="number"
        value={value || platformRefreshInterval}
        onChange={changeInput}
      />
      <Typography>{pluralize(t(labelSeconds), value)}</Typography>
    </div>
  );
};

export default GlobalRefreshFieldOption;
