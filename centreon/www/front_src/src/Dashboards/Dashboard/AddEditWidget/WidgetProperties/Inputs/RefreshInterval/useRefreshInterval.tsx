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
import { RadioOptions } from '../../../models';

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
      [equals<RadioOptions>(RadioOptions.default), always(defaultInterval)],
      [equals<RadioOptions>(RadioOptions.custom), always(customInterval)],
      [equals<RadioOptions>(RadioOptions.manual), always(null)]
    ])(event.target.value as RadioOptions);

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
      label: `${t(
        labelDashboardGlobalInterval
      )} (${defaultInterval} ${pluralize(t(labelSecond), defaultInterval)})`,
      value: RadioOptions.default
    },
    {
      label: (
        <Box className={classes.customInterval}>
          <Typography>{t(labelCustomRefreshInterval)}</Typography>
          <div>
            <TextField
              className={classes.customIntervalField}
              dataTestId={labelInterval}
              disabled={!equals(RadioOptions.custom, value)}
              inputProps={{
                min: 0
              }}
              size="compact"
              type="number"
              value={`${customInterval}`}
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

  return {
    changeRefreshIntervalOption,
    options,
    value
  };
};

export default useRefreshInterval;
