import { ChangeEvent, useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  equals,
  filter,
  flatten,
  has,
  head,
  length,
  pipe,
  pluck,
  uniq
} from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { formatMetricValueWithUnit } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { WidgetTextField } from '..';
import {
  labelCriticalThreshold,
  labelCustom,
  labelDefault,
  labelDefaultValueIsDefinedByFirstMetricUsed,
  labelNone,
  labelThresholds,
  labelWarningThreshold
} from '../../../../translatedLabels';
import { Metric, RadioOptions, ServiceMetric } from '../../../models';
import { useThresholdStyles } from '../Inputs.styles';
import { getDataProperty, getProperty } from '../utils';

interface UseThresholdProps {
  propertyName: string;
}

interface UseThresholdState {
  changeBaseColor: (color: string) => () => void;
  changeCustom: (
    threshold: string
  ) => (event: ChangeEvent<HTMLInputElement>) => void;
  changeType: (
    threshold: string
  ) => (event: ChangeEvent<HTMLInputElement>) => void;
  criticalCustom: number | undefined;
  criticalType: string | undefined;
  customBaseColor?: string;
  customWarning: number | undefined;
  enabled: boolean | undefined;
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
): ((metrics: Array<Metric>) => number | null) =>
  pipe(
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
  const customBaseColorProp = `${propertyName}.baseColor`;

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

  const enabled = useMemo<boolean | undefined>(
    () => getProperty({ obj: values, propertyName: enabledProp }),
    [getProperty({ obj: values, propertyName: enabledProp })]
  );

  const customBaseColor = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName: customBaseColorProp }),
    [getProperty({ obj: values, propertyName: customBaseColorProp })]
  );

  const warningType = getThresholdType('warning');
  const customWarning = getThresholdCustom('Warning');
  const criticalType = getThresholdType('critical');
  const criticalCustom = getThresholdCustom('Critical');

  const metrics = useMemo<Array<ServiceMetric> | Array<Metric> | undefined>(
    () => getDataProperty({ obj: values, propertyName: 'metrics' }),
    [getDataProperty({ obj: values, propertyName: 'metrics' })]
  );

  const isServiceMetric = has('metrics', metrics?.[0]);

  const isMaxSelectedUnitReached = isServiceMetric
    ? pipe(
        pluck('metrics'),
        flatten,
        pluck('unit'),
        uniq,
        length,
        equals(2)
      )((metrics as Array<ServiceMetric>) || [])
    : pipe(
        pluck('unit'),
        uniq,
        length,
        equals(2)
      )((metrics as Array<Metric>) || []);

  const formattedMetrics = isServiceMetric
    ? pipe(pluck('metrics'), flatten)((metrics as Array<ServiceMetric>) || [])
    : (metrics as Array<Metric>) || [];

  const metric = head(formattedMetrics as Array<Metric>);

  const formatThreshold = (threshold: number | null): string => {
    if (!threshold) {
      return t(labelNone);
    }

    return (
      formatMetricValueWithUnit({
        unit: metric?.unit || '',
        value: threshold || null
      }) || ''
    );
  };

  const firstWarningHighThreshold = getMetricThreshold('warningHighThreshold')(
    formattedMetrics
  );

  const firstWarningLowThreshold = getMetricThreshold('warningLowThreshold')(
    formattedMetrics
  );

  const firstCriticalHighThreshold = getMetricThreshold(
    'criticalHighThreshold'
  )(formattedMetrics);

  const firstCriticalLowThreshold = getMetricThreshold('criticalLowThreshold')(
    formattedMetrics
  );

  const warningDefaultThresholdLabel = firstWarningLowThreshold
    ? `(${formatThreshold(firstWarningLowThreshold)} - ${formatThreshold(
        firstWarningHighThreshold
      )})`
    : `(${formatThreshold(firstWarningHighThreshold)})`;
  const criticalDefaultThresholdLabel = firstCriticalLowThreshold
    ? `(${formatThreshold(firstCriticalLowThreshold)} - ${formatThreshold(
        firstCriticalHighThreshold
      )})`
    : `(${formatThreshold(firstCriticalHighThreshold)})`;

  const isDefault = equals<RadioOptions | undefined>(RadioOptions.default);

  const options = [
    {
      label: labelWarningThreshold,
      radioButtons: [
        {
          content: (
            <Tooltip
              followCursor={false}
              label={t(labelDefaultValueIsDefinedByFirstMetricUsed)}
              position="bottom"
            >
              <Typography>
                {`${t(labelDefault)} ${warningDefaultThresholdLabel}`}
              </Typography>
            </Tooltip>
          ),
          value: RadioOptions.default
        },
        {
          content: (
            <Box className={classes.customThreshold}>
              <Typography>{t(labelCustom)}</Typography>
              {!isDefault(warningType) && (
                <>
                  <WidgetTextField
                    label={t(labelThresholds)}
                    propertyName={`${propertyName}.customWarning`}
                    text={{
                      autoSize: true,
                      size: 'compact',
                      step: '0.01',
                      type: 'number'
                    }}
                  />
                  <Typography>
                    (
                    {formatMetricValueWithUnit({
                      unit: metric?.unit || '',
                      value: customWarning || 0
                    })}
                    )
                  </Typography>
                </>
              )}
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
          content: (
            <Tooltip
              followCursor={false}
              label={t(labelDefaultValueIsDefinedByFirstMetricUsed)}
              position="bottom"
            >
              <Typography>
                {`${t(labelDefault)} ${criticalDefaultThresholdLabel}`}
              </Typography>
            </Tooltip>
          ),
          value: RadioOptions.default
        },
        {
          content: (
            <Box className={classes.customThreshold}>
              <Typography>{t(labelCustom)}</Typography>
              {!isDefault(criticalType) && (
                <>
                  <WidgetTextField
                    label={t(labelThresholds)}
                    propertyName={`${propertyName}.customCritical`}
                    text={{
                      autoSize: true,
                      size: 'compact',
                      step: '0.01',
                      type: 'number'
                    }}
                  />
                  <Typography>
                    (
                    {formatMetricValueWithUnit({
                      unit: metric?.unit || '',
                      value: criticalCustom || 0
                    })}
                    )
                  </Typography>
                </>
              )}
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

  const changeBaseColor = (color: string) => (): void => {
    setFieldValue(`options.${customBaseColorProp}`, color);
  };

  useEffect(() => {
    if (!isMaxSelectedUnitReached) {
      return;
    }

    setFieldValue(`options.${enabledProp}`, false);
  }, [isMaxSelectedUnitReached]);

  return {
    changeBaseColor,
    changeCustom,
    changeType,
    criticalCustom,
    criticalType,
    customBaseColor,
    customWarning,
    enabled,
    options,
    warningType
  };
};

export default useThreshold;
