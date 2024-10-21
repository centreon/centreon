import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  LineChartData,
  formatMetricValueWithUnit,
  getMetricWithLatestData
} from '@centreon/ui';

import { FormThreshold } from './models';
import {
  labelCriticalThreshold,
  labelCustomValue,
  labelValueDefinedByMetric,
  labelWarningThreshold
} from './translatedLabels';

interface Props {
  data?: LineChartData;
  displayAsRaw?: boolean;
  metricName?: string;
  thresholds: FormThreshold;
}

interface UseThresholdsState {
  critical: Array<{
    label: string;
    value: number | null;
  }>;
  enabled: boolean;
  warning: Array<{
    label: string;
    value: number | null;
  }>;
}

const useThresholds = ({
  thresholds,
  data,
  metricName = '',
  displayAsRaw
}: Props): UseThresholdsState | undefined => {
  const { t } = useTranslation();

  const isDefaultWarning = equals(thresholds.warningType, 'default');
  const isDefaultCritical = equals(thresholds.criticalType, 'default');

  if (isEmpty(data?.metrics)) {
    return undefined;
  }

  const warningThresholds = isDefaultWarning
    ? [
        data?.metrics[0].warning_low_threshold,
        data?.metrics[0].warning_high_threshold
      ].filter((threshold) => threshold)
    : [thresholds.customWarning];

  const criticalThresholds = isDefaultCritical
    ? [
        data?.metrics[0].critical_low_threshold,
        data?.metrics[0].critical_high_threshold
      ].filter((threshold) => threshold)
    : [thresholds.customCritical];

  const metric = data ? getMetricWithLatestData(data) : null;

  const formattedWarningThresholds = warningThresholds.map((threshold) => {
    const formattedThreshold = formatMetricValueWithUnit({
      isRaw: displayAsRaw,
      unit: metric?.unit || '',
      value: threshold || 0
    });

    return {
      label: isDefaultWarning
        ? `${t(labelWarningThreshold)}: ${formattedThreshold}. ${t(
            labelValueDefinedByMetric,
            { metric: metricName }
          )}`
        : `${t(labelWarningThreshold)}: ${formattedThreshold}. ${t(
            labelCustomValue
          )}`,
      value: threshold || 0
    };
  });

  const formattedCriticalThresholds = criticalThresholds.map((threshold) => {
    const formattedThreshold = formatMetricValueWithUnit({
      isRaw: displayAsRaw,
      unit: metric?.unit || '',
      value: threshold || 0
    });

    return {
      label: isDefaultCritical
        ? `${t(labelCriticalThreshold)}: ${formattedThreshold}. ${t(
            labelValueDefinedByMetric,
            { metric: metricName }
          )}`
        : `${t(labelCriticalThreshold)}: ${formattedThreshold}. ${t(
            labelCustomValue
          )}`,
      value: threshold || 0
    };
  });

  const hasThresholds = [...warningThresholds, ...criticalThresholds].some(
    (threshold) => threshold
  );

  return {
    critical: formattedCriticalThresholds,
    enabled: thresholds.enabled && hasThresholds,
    warning: formattedWarningThresholds
  };
};

export default useThresholds;
