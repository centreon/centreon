import { isNil } from 'ramda';

import { Typography, useTheme } from '@mui/material';

import { LineChartData } from '../common/models';
import {
  formatMetricValueWithUnit,
  getMetricWithLatestData
} from '../common/timeSeries';
import { getColorFromDataAndTresholds } from '../common/utils';

import { useTextStyles } from './Text.styles';

interface Props {
  data?: LineChartData;
  disabledThresholds?: boolean;
  displayAsRaw?: boolean;
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
  disabledThresholds,
  displayAsRaw
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
        {formatMetricValueWithUnit({
          isRaw: displayAsRaw,
          unit: metricUnit,
          value: metricValue
        })}
      </Typography>
      {!disabledThresholds && (
        <div className={classes.thresholds}>
          <Typography sx={{ color: 'warning.main' }} variant="h5">
            {labels.warning}
            {': '}
            {formatMetricValueWithUnit({
              isRaw: displayAsRaw,
              unit: metricUnit,
              value: thresholds[0]
            })}
          </Typography>
          <Typography sx={{ color: 'error.main' }} variant="h5">
            {labels.critical}
            {': '}
            {formatMetricValueWithUnit({
              isRaw: displayAsRaw,
              unit: metricUnit,
              value: thresholds[1]
            })}
          </Typography>
        </div>
      )}
    </div>
  );
};
