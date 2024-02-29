import { isNil } from 'ramda';

import { Typography, useTheme } from '@mui/material';

import { LineChartData, Thresholds } from '../common/models';
import {
  formatMetricValueWithUnit,
  getMetricWithLatestData
} from '../common/timeSeries';
import { getColorFromDataAndTresholds } from '../common/utils';

import { useTextStyles } from './Text.styles';

export interface Props {
  baseColor?: string;
  data?: LineChartData;
  displayAsRaw?: boolean;
  labels: {
    critical: string;
    warning: string;
  };
  thresholds: Thresholds;
}

export const Text = ({
  thresholds,
  data,
  displayAsRaw,
  labels,
  baseColor
}: Props): JSX.Element | null => {
  const theme = useTheme();
  const { classes } = useTextStyles();

  if (isNil(data)) {
    return null;
  }

  const metric = getMetricWithLatestData(data);
  const metricValue = metric?.data[0] ?? 0;
  const metricUnit = metric?.unit ?? '';

  const color = getColorFromDataAndTresholds({
    baseColor,
    data: metricValue,
    theme,
    thresholds
  });

  const warningThresholdLabels = thresholds.warning.map(({ value }) =>
    formatMetricValueWithUnit({
      unit: metricUnit,
      value
    })
  );

  const criticalThresholdLabels = thresholds.critical.map(({ value }) =>
    formatMetricValueWithUnit({
      unit: metricUnit,
      value
    })
  );

  return (
    <div className={classes.graphText}>
      <Typography sx={{ color }} variant="h2">
        <strong>
          {formatMetricValueWithUnit({
            isRaw: displayAsRaw,
            unit: metricUnit,
            value: metricValue
          })}
        </strong>
      </Typography>
      {thresholds.enabled && (
        <div className={classes.thresholds}>
          <Typography sx={{ color: 'warning.main' }} variant="h5">
            {labels.warning}
            {': '}
            {warningThresholdLabels.join(' - ')}
          </Typography>
          <Typography sx={{ color: 'error.main' }} variant="h5">
            {labels.critical}
            {': '}
            {criticalThresholdLabels.join(' - ')}
          </Typography>
        </div>
      )}
    </div>
  );
};
