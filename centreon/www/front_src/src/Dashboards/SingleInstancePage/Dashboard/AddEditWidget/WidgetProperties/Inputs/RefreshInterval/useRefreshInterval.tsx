/* eslint-disable hooks/sort */
import { ChangeEvent, ReactNode, useEffect, useMemo, useState } from 'react';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import pluralize from 'pluralize';
import { always, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { NumberField } from '@centreon/ui';
import { refreshIntervalAtom } from '@centreon/ui-context';

import { dashboardRefreshIntervalAtom } from '../../../../atoms';
import {
  labelCustomRefreshInterval,
  labelDashboardGlobalInterval,
  labelInterval,
  labelManual,
  labelManualRefresh,
  labelSecond
} from '../../../../translatedLabels';
import { RadioOptions } from '../../../models';
import { useRefreshIntervalStyles } from '../Inputs.styles';
import { getProperty } from '../utils';

interface UseRefreshIntervalState {
  changeRefreshIntervalOption: (event: ChangeEvent<HTMLInputElement>) => void;
  options: Array<{
    label: string | ReactNode;
    value: RadioOptions;
  }>;
  value?: string;
}

const useRefreshInterval = ({ propertyName }): UseRefreshIntervalState => {
  const { t } = useTranslation();
  const { classes } = useRefreshIntervalStyles();

  const refreshIntervalCustomProperty = `${propertyName}Custom`;

  const { values, setFieldValue } = useFormikContext();

  const platformRefreshInterval = useAtomValue(refreshIntervalAtom);
  const dashboardRefreshInterval = useAtomValue(dashboardRefreshIntervalAtom);

  const defaultInterval =
    dashboardRefreshInterval?.interval || platformRefreshInterval;

  const [customInterval, setCustomInterval] = useState(
    getProperty({ obj: values, propertyName: refreshIntervalCustomProperty }) ||
      defaultInterval
  );

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const changeRefreshIntervalOption = (
    event: ChangeEvent<HTMLInputElement>
  ): void => {
    setFieldValue(`options.${propertyName}`, event.target.value);

    const newInterval = cond([
      [equals<RadioOptions>(RadioOptions.default), always(defaultInterval)],
      [equals<RadioOptions>(RadioOptions.custom), always(customInterval)],
      [equals<RadioOptions>(RadioOptions.manual), always(null)]
    ])(event.target.value as RadioOptions);

    setFieldValue(`options.${refreshIntervalCustomProperty}`, newInterval);
  };

  const changeCustomRefreshInterval = (inputValue: number): void => {
    setCustomInterval(inputValue);
    setFieldValue(`options.${refreshIntervalCustomProperty}`, inputValue);
  };

  const defaultLabel = equals(dashboardRefreshInterval?.type, 'manual')
    ? t(labelManual)
    : `${defaultInterval} ${pluralize(t(labelSecond), defaultInterval)}`;

  const options = [
    {
      label: `${t(labelDashboardGlobalInterval)} (${defaultLabel})`,
      value: RadioOptions.default
    },
    {
      label: (
        <Box className={classes.customInterval}>
          <Typography>{t(labelCustomRefreshInterval)}</Typography>
          <div>
            <NumberField
              className={classes.customIntervalField}
              dataTestId={labelInterval}
              defaultValue={customInterval}
              disabled={!equals(RadioOptions.custom, value)}
              fallbackValue={defaultInterval}
              inputProps={{
                min: 1
              }}
              size="compact"
              type="number"
              onChange={changeCustomRefreshInterval}
            />
          </div>
          <Typography>{pluralize(t(labelSecond), customInterval)}</Typography>
        </Box>
      ),
      value: RadioOptions.custom
    },
    {
      label: t(labelManualRefresh),
      value: RadioOptions.manual
    }
  ];

  useEffect(() => {
    if (!isNil(defaultInterval)) {
      return;
    }
    setFieldValue(`options.${refreshIntervalCustomProperty}`, defaultInterval);
    setCustomInterval(defaultInterval);
  }, []);

  return {
    changeRefreshIntervalOption,
    options,
    value
  };
};

export default useRefreshInterval;
