import { Box } from '@mui/material';
import { LineChartData, Thresholds } from '../common/models';
import { getMetricWithLatestData } from '../common/timeSeries';
import { Metric } from '../common/timeSeries/models';

import ResponsiveGauge from './ResponsiveGauge';
import { useResizeObserver } from './useResizeObserver';

export interface Props {
  baseColor?: string;
  data?: LineChartData;
  displayAsRaw?: boolean;
  thresholds: Thresholds;
  max?: number;
}

export const Gauge = ({
  thresholds,
  data,
  displayAsRaw,
  baseColor,
  max
}: Props): JSX.Element | null => {
  const { width, height, ref } = useResizeObserver();

  if (!data) {
    return null;
  }

  const metric = getMetricWithLatestData(data) as Metric;

  return (
    <Box sx={{ width: '100%', height: '100%' }} ref={ref}>
      <ResponsiveGauge
        baseColor={baseColor}
        displayAsRaw={displayAsRaw}
        height={height}
        metric={metric}
        thresholds={thresholds}
        width={width}
        max={max}
      />
    </Box>
  );
};
