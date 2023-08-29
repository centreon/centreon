import { isNil } from 'ramda';

import { Typography, useTheme } from '@mui/material';

import { LineChartData } from '../common/models';
import {
  formatMetricValue,
  getMetricWithLatestData
} from '../common/timeSeries';
import { getColorFromDataAndTresholds } from '../common/utils';

import { useTextStyles } from './Text.styles';

interface Props {
  data?: LineChartData;
  disabledThresholds?: boolean;
  labels: {
    critical: string;
    warning: string;
  };
  thresholds: Array<number>;
}

export const Text = ({
  thresholds,
  data,
  labels,
  disabledThresholds
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

  return (
    <div className={classes.graphText}>
      <Typography sx={{ color }} variant="h3">
        {formatMetricValue({
          unit: metricUnit,
          value: metricValue
        })}{' '}
        {metricUnit}
      </Typography>
      {!disabledThresholds && (
        <div className={classes.thresholds}>
          <Typography sx={{ color: 'warning.main' }} variant="h5">
            {labels.warning}
            {': '}
            {formatMetricValue({
              unit: metricUnit,
              value: thresholds[0]
            })}{' '}
            {metricUnit}
          </Typography>
          <Typography sx={{ color: 'error.main' }} variant="h5">
            {labels.critical}
            {': '}
            {formatMetricValue({
              unit: metricUnit,
              value: thresholds[1]
            })}{' '}
            {metricUnit}
          </Typography>
        </div>
      )}
    </div>
  );
};
