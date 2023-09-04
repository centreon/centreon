import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  LineChartData,
  formatMetricValue,
  getMetricWithLatestData
} from '@centreon/ui';

import { FormThreshold } from './models';
import {
  labelCriticalThreshold,
  labelValueCustomized,
  labelValueDefinedByMetric,
  labelWarningThreshold
} from './translatedLabels';

interface Props {
  data?: LineChartData;
  metricName?: string;
  thresholds: FormThreshold;
}

interface UseThresholdsState {
  critical: Array<{
    label: string;
    value: number;
  }>;
  enabled: boolean;
  warning: Array<{
    label: string;
    value: number;
  }>;
}

const useThresholds = ({
  thresholds,
  data,
  metricName = ''
}: Props): UseThresholdsState => {
  const { t } = useTranslation();

  const isDefaultWarning = equals(thresholds.warningType, 'default');
  const isDefaultCritical = equals(thresholds.criticalType, 'default');

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
    const formattedThreshold = formatMetricValue({
      unit: metric?.unit || '',
      value: threshold
    });

    return {
      label: isDefaultWarning
        ? `${t(labelWarningThreshold)}: ${formattedThreshold} ${
            metric?.unit
          }. ${t(labelValueDefinedByMetric)} ${metricName}`
        : `${t(labelWarningThreshold)}: ${formattedThreshold} ${
            metric?.unit
          }. ${t(labelValueCustomized)}`,
      value: threshold
    };
  });

  const formattedCriticalThresholds = criticalThresholds.map((threshold) => {
    const formattedThreshold = formatMetricValue({
      unit: metric?.unit || '',
      value: threshold
    });

    return {
      label: isDefaultCritical
        ? `${t(labelCriticalThreshold)}: ${formattedThreshold} ${
            metric?.unit
          }. ${t(labelValueDefinedByMetric)} ${metricName}`
        : `${t(labelCriticalThreshold)}: ${formattedThreshold} ${
            metric?.unit
          }. ${t(labelValueCustomized)}`,
      value: threshold
    };
  });

  return {
    critical: formattedCriticalThresholds,
    enabled: thresholds.enabled,
    warning: formattedWarningThresholds
  };
};

export default useThresholds;
