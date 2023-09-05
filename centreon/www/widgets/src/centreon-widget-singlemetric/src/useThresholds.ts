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
  thresholdLabels: Array<string>;
  thresholdValues: Array<number>;
}

const useThresholds = ({
  thresholds,
  data,
  metricName = ''
}: Props): UseThresholdsState => {
  const { t } = useTranslation();

  const isDefaultWarning = equals(thresholds.warningType, 'default');
  const isDefaultCritical = equals(thresholds.criticalType, 'default');

  const warning = isDefaultWarning
    ? data?.metrics[0].warn || 0
    : thresholds.customWarning;
  const critical = isDefaultCritical
    ? data?.metrics[0].crit || 0
    : thresholds.customCritical;

  if (!warning && !critical) {
    return {
      thresholdLabels: [],
      thresholdValues: []
    };
  }

  const metric = data ? getMetricWithLatestData(data) : null;

  const formattedWarning = formatMetricValue({
    unit: metric?.unit || '',
    value: warning
  });
  const formattedCritical = formatMetricValue({
    unit: metric?.unit || '',
    value: critical
  });

  const thresholdValues = [warning, critical];
  const thresholdLabels = [
    isDefaultWarning
      ? `${t(labelWarningThreshold)}: ${formattedWarning} ${metric?.unit}. ${t(
          labelValueDefinedByMetric
        )} ${metricName}`
      : `${t(labelWarningThreshold)}: ${formattedWarning} ${metric?.unit}. ${t(
          labelValueCustomized
        )}`,
    isDefaultCritical
      ? `${t(labelCriticalThreshold)}: ${formattedCritical} ${
          metric?.unit
        }. ${t(labelValueDefinedByMetric)} ${metricName}`
      : `${t(labelCriticalThreshold)}: ${formattedCritical} ${
          metric?.unit
        }. ${t(labelValueCustomized)}`
  ];

  return {
    thresholdLabels,
    thresholdValues
  };
};

export default useThresholds;
