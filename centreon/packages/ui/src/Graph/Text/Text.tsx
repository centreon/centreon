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
import { useRef, type ReactElement } from 'react';
import useResizeObserver from 'use-resize-observer';

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
  minThresholds?: string;
}

export const Text = ({
  thresholds,
  data,
  displayAsRaw,
  labels,
  baseColor,
  prefThresholds = 14,
  minThresholds
}: Props): ReactElement | null => {
  const theme = useTheme();
  const { classes, cx } = useTextStyles();
  const { ref, width = 0 } = useResizeObserver();

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

  const canDisplayThresholdLabel = width > 150;
  const warningLabel = canDisplayThresholdLabel ? `${labels.warning}: ` : '';
  const criticalLabel = canDisplayThresholdLabel ? `${labels.critical}: ` : '';

  return (
    <div className={classes.graphText} ref={ref}>
      <FluidTypography
        max="40px"
        pref={14}
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
            containerClassName={cx(classes.thresholdLabel, classes.warning)}
            max="30px"
            pref={prefThresholds}
            text={`${warningLabel}${warningThresholdLabels.join(' - ')}`}
            variant="h5"
            min={minThresholds}
          />
          <FluidTypography
            containerClassName={cx(classes.thresholdLabel, classes.critical)}
            max="30px"
            pref={prefThresholds}
            text={`${criticalLabel}${criticalThresholdLabels.join(' - ')}`}
            variant="h5"
            min={minThresholds}
          />
        </div>
      )}
    </div>
  );
};
