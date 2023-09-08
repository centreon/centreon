import { isNil } from 'ramda';

import { Typography, useTheme } from '@mui/material';

import { LineChartData, Thresholds } from '../common/models';
import {
  formatMetricValue,
  getMetricWithLatestData
} from '../common/timeSeries';
import { getColorFromDataAndTresholds } from '../common/utils';

import { useTextStyles } from './Text.styles';

interface Props {
  data?: LineChartData;
  labels: {
    critical: string;
    warning: string;
  };
  thresholds: Thresholds;
}

export const Text = ({
  thresholds,
  data,
  labels
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
    data: metricValue,
    theme,
    thresholds
  });

  const warningThresholdLabels = thresholds.warning.map(({ value }) =>
    formatMetricValue({
      unit: metricUnit,
      value
    })
  );

  const criticalThresholdLabels = thresholds.critical.map(({ value }) =>
    formatMetricValue({
      unit: metricUnit,
      value
    })
  );

  return (
    <div className={classes.graphText}>
      <Typography sx={{ color }} variant="h3">
        {formatMetricValue({
          unit: metricUnit,
          value: metricValue
        })}{' '}
        {metricUnit}
      </Typography>
      {thresholds.enabled && (
        <div className={classes.thresholds}>
          <Typography sx={{ color: 'warning.main' }} variant="h5">
            {labels.warning}
            {': '}
            {warningThresholdLabels.join(' - ')} {metricUnit}
          </Typography>
          <Typography sx={{ color: 'error.main' }} variant="h5">
            {labels.critical}
            {': '}
            {criticalThresholdLabels.join(' - ')} {metricUnit}
          </Typography>
        </div>
      )}
    </div>
  );
};
