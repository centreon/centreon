import { isNil } from 'ramda';

import { useTheme } from '@mui/material';

import FluidTypography from '../../Typography/FluidTypography';
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
  prefThresholds?: number;
}

export const Text = ({
  thresholds,
  data,
  displayAsRaw,
  labels,
  baseColor,
  prefThresholds = 14
}: Props): JSX.Element | null => {
  const theme = useTheme();
  const { classes, cx } = useTextStyles();

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
      <FluidTypography
        max="60px"
        pref={16}
        sx={{ color, fontWeight: 'bold', textAlign: 'center' }}
        text={
          formatMetricValueWithUnit({
            isRaw: displayAsRaw,
            unit: metricUnit,
            value: metricValue
          }) || ''
        }
        variant="h2"
      />
      {thresholds.enabled && (
        <div className={classes.thresholds}>
          <FluidTypography
            containerClassName={cx(classes.thresholdLeft, classes.warning)}
            max="30px"
            pref={prefThresholds}
            text={`${labels.warning}: ${warningThresholdLabels.join(' - ')}`}
            variant="h5"
          />
          <FluidTypography
            containerClassName={cx(classes.thresholdRight, classes.critical)}
            max="30px"
            pref={prefThresholds}
            text={`${labels.critical}: ${criticalThresholdLabels.join(' - ')}`}
            variant="h5"
          />
        </div>
      )}
    </div>
  );
};
