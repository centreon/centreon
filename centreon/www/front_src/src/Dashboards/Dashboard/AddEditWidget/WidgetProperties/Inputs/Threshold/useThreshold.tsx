import { ChangeEvent, useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { equals, flatten, head, length, pipe, pluck, uniq } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { formatMetricValueWithUnit } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { getDataProperty, getProperty } from '../utils';
import { Metric, RadioOptions, ServiceMetric } from '../../../models';
import {
  labelCriticalThreshold,
  labelCustom,
  labelDefault,
  labelDefaultValueIsDefinedByFirstMetricUsed,
  labelNone,
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
): ((metrics: Array<ServiceMetric>) => number | null) =>
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

  const metric = pipe(pluck('metrics'), flatten, head)(metrics || []) as
    | Metric
    | undefined;

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

  const firstWarningHighThreshold = formatThreshold(
    getMetricThreshold('warningHighThreshold')(metrics || [])
  );
  const firstWarningLowThreshold = formatThreshold(
    getMetricThreshold('warningLowThreshold')(metrics || [])
  );

  const firstCriticalHighThreshold = formatThreshold(
    getMetricThreshold('criticalHighThreshold')(metrics || [])
  );
  const firstCriticalLowThreshold = formatThreshold(
    getMetricThreshold('criticalLowThreshold')(metrics || [])
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
                    label={t(labelThreshold)}
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
                    label={t(labelThreshold)}
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
