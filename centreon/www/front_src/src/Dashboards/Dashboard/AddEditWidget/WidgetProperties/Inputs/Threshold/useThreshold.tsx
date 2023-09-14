import { ChangeEvent, useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  equals,
  filter,
  flatten,
  head,
  length,
  pipe,
  pluck,
  uniq
} from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { getDataProperty, getProperty } from '../utils';
import { RadioOptions, ServiceMetric } from '../../../models';
import {
  labelCriticalThreshold,
  labelCustom,
  labelDefault,
  labelThreshold,
  labelWarningThreshold
} from '../../../../translatedLabels';
import { WidgetTextField } from '..';
import { useThresholdStyles } from '../Inputs.styles';

interface UseThresholdProps {
  propertyName: string;
}

interface UseThresholdState {
  changeCustom: (
    threshold: string
  ) => (event: ChangeEvent<HTMLInputElement>) => void;
  changeType: (
    threshold: string
  ) => (event: ChangeEvent<HTMLInputElement>) => void;
  criticalCustom: number | undefined;
  criticalType: string | undefined;
  customWarning: number | undefined;
  enabled: string | undefined;
  options: Array<{
    label: string;
    radioButtons: Array<{
      content: JSX.Element | string;
      value: string;
    }>;
    type: string;
    value?: string;
  }>;
  warningType: string | undefined;
}

const getMetricThreshold = (
  thresholdType: string
): ((metrics: Array<ServiceMetric>) => unknown) =>
  pipe(
    pluck('metrics'),
    flatten,
    pluck(thresholdType),
    filter((threshold) => !!threshold),
    head
  );

const useThreshold = ({
  propertyName
}: UseThresholdProps): UseThresholdState => {
  const { t } = useTranslation();
  const { classes } = useThresholdStyles();

  const { values, setFieldValue } = useFormikContext();

  const enabledProp = `${propertyName}.enabled`;

  const getThresholdType = (threshold: string): RadioOptions | undefined =>
    getProperty({
      obj: values,
      propertyName: `${propertyName}.${threshold}Type`
    });

  const getThresholdCustom = (threshold: string): number | undefined =>
    getProperty({
      obj: values,
      propertyName: `${propertyName}.custom${threshold}`
    });

  const enabled = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName: enabledProp }),
    [getProperty({ obj: values, propertyName: enabledProp })]
  );

  const warningType = getThresholdType('warning');
  const customWarning = getThresholdCustom('Warning');
  const criticalType = getThresholdType('critical');
  const criticalCustom = getThresholdCustom('Critical');

  const metrics = useMemo<Array<ServiceMetric> | undefined>(
    () => getDataProperty({ obj: values, propertyName: 'metrics' }),
    [getDataProperty({ obj: values, propertyName: 'metrics' })]
  );

  const isMaxSelectedUnitReached = pipe(
    pluck('metrics'),
    flatten,
    pluck('unit'),
    uniq,
    length,
    equals(2)
  )(metrics || []);

  const firstWarningHighThreshold = getMetricThreshold('warningHighThreshold')(
    metrics || []
  );
  const firstWarningLowThreshold = getMetricThreshold('warningLowThreshold')(
    metrics || []
  );

  const firstCriticalHighThreshold = getMetricThreshold(
    'criticalHighThreshold'
  )(metrics || []);
  const firstCriticalLowThreshold = getMetricThreshold('criticalLowThreshold')(
    metrics || []
  );

  const warningDefaultThresholdLabel = firstWarningLowThreshold
    ? `(${firstWarningLowThreshold} - ${firstWarningHighThreshold})`
    : `(${firstWarningHighThreshold || ''})`;
  const criticalDefaultThresholdLabel = firstCriticalLowThreshold
    ? `(${firstCriticalLowThreshold} - ${firstCriticalHighThreshold})`
    : `(${firstCriticalHighThreshold || ''})`;

  const isDefault = equals<RadioOptions | undefined>(RadioOptions.default);

  const options = [
    {
      label: labelWarningThreshold,
      radioButtons: [
        {
          content: `${t(labelDefault)} ${warningDefaultThresholdLabel}`,
          value: RadioOptions.default
        },
        {
          content: (
            <Box className={classes.threshold}>
              <Typography>{t(labelCustom)}</Typography>
              <WidgetTextField
                className={classes.thresholdField}
                disabled={isDefault(warningType)}
                label={t(labelThreshold)}
                propertyName={`${propertyName}.customWarning`}
                text={{ size: 'compact', step: '0.01', type: 'number' }}
              />
            </Box>
          ),
          value: RadioOptions.custom
        }
      ],
      type: 'warning',
      value: warningType
    },
    {
      label: labelCriticalThreshold,
      radioButtons: [
        {
          content: `${t(labelDefault)} ${criticalDefaultThresholdLabel}`,
          value: RadioOptions.default
        },
        {
          content: (
            <Box className={classes.threshold}>
              <Typography>{t(labelCustom)}</Typography>
              <WidgetTextField
                className={classes.thresholdField}
                disabled={isDefault(criticalType)}
                label={t(labelThreshold)}
                propertyName={`${propertyName}.customCritical`}
                text={{ size: 'compact', step: '0.01', type: 'number' }}
              />
            </Box>
          ),
          value: RadioOptions.custom
        }
      ],
      type: 'critical',
      value: criticalType
    }
  ];

  const changeType =
    (threshold: string) =>
    (event: ChangeEvent<HTMLInputElement>): void => {
      setFieldValue(
        `options.${propertyName}.${threshold}Type`,
        event.target.value
      );
    };

  const changeCustom =
    (threshold: string) =>
    (event: ChangeEvent<HTMLInputElement>): void => {
      setFieldValue(
        `options.${propertyName}.custom${threshold}`,
        event.target.value
      );
    };

  useEffect(() => {
    if (!isMaxSelectedUnitReached) {
      return;
    }

    setFieldValue(`options.${enabledProp}`, false);
  }, [isMaxSelectedUnitReached]);

  return {
    changeCustom,
    changeType,
    criticalCustom,
    criticalType,
    customWarning,
    enabled,
    options,
    warningType
  };
};

export default useThreshold;
