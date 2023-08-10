import { ChangeEvent, ReactNode, useMemo, useState } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import pluralize from 'pluralize';
import { always, cond, equals } from 'ramda';
import { useAtomValue } from 'jotai';

import { Box, Typography } from '@mui/material';

import { TextField } from '@centreon/ui';

import { getProperty } from '../utils';
import {
  labelCustomRefreshInterval,
  labelDashboardGlobalInterval,
  labelInterval,
  labelManualRefresh,
  labelSecond
} from '../../../../translatedLabels';
import { refreshIntervalAtom } from '../../../../atoms';
import { useRefreshIntervalStyles } from '../Inputs.styles';

enum RefreshIntervalOptions {
  custom = 'custom',
  default = 'default',
  manual = 'manual'
}

interface UseRefreshIntervalState {
  changeRefreshIntervalOption: (event: ChangeEvent<HTMLInputElement>) => void;
  options: Array<{
    label: string | ReactNode;
    value: RefreshIntervalOptions;
  }>;
  value?: string;
}

const useRefreshInterval = ({ propertyName }): UseRefreshIntervalState => {
  const { t } = useTranslation();
  const { classes } = useRefreshIntervalStyles();

  const refreshIntervalCountProperty = `${propertyName}Count`;

  const { values, setFieldValue } = useFormikContext();

  const [customInterval, setCustomInterval] = useState(
    getProperty({ obj: values, propertyName: refreshIntervalCountProperty }) ||
      0
  );

  const defaultInterval = useAtomValue(refreshIntervalAtom);

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const changeRefreshIntervalOption = (
    event: ChangeEvent<HTMLInputElement>
  ): void => {
    setFieldValue(`options.${propertyName}`, event.target.value);

    const newInterval = cond([
      [
        equals<RefreshIntervalOptions>(RefreshIntervalOptions.default),
        always(defaultInterval)
      ],
      [
        equals<RefreshIntervalOptions>(RefreshIntervalOptions.custom),
        always(customInterval)
      ],
      [
        equals<RefreshIntervalOptions>(RefreshIntervalOptions.manual),
        always(null)
      ]
    ])(event.target.value as RefreshIntervalOptions);

    setFieldValue(`options.${refreshIntervalCountProperty}`, newInterval);
  };

  const changeCustomRefreshInterval = (
    event: ChangeEvent<HTMLInputElement>
  ): void => {
    const newInterval = parseInt(event.target.value || '0', 10);

    setCustomInterval(newInterval);
    setFieldValue(`options.${refreshIntervalCountProperty}`, newInterval);
  };

  const options = [
    {
      label: t(labelDashboardGlobalInterval),
      value: RefreshIntervalOptions.default
    },
    {
      label: (
        <Box className={classes.customInterval}>
          <Typography>{t(labelCustomRefreshInterval)}</Typography>
          <TextField
            className={classes.customIntervalField}
            dataTestId={labelInterval}
            disabled={!equals(RefreshIntervalOptions.custom, value)}
            label={t(labelInterval)}
            size="compact"
            type="number"
            value={`${customInterval}`}
            onChange={changeCustomRefreshInterval}
          />
          <Typography>{pluralize(t(labelSecond), customInterval)}</Typography>
        </Box>
      ),
      value: RefreshIntervalOptions.custom
    },
    {
      label: t(labelManualRefresh),
      value: RefreshIntervalOptions.manual
    }
  ];

  return {
    changeRefreshIntervalOption,
    options,
    value
  };
};

export default useRefreshInterval;
