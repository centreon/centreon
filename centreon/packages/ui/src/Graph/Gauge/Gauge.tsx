import { Responsive } from '@visx/visx';
import { sort, subtract } from 'ramda';

import { LineChartData } from '../common/models';
import { getMetricWithLatestData } from '../common/timeSeries';
import { Metric } from '../common/timeSeries/models';

import ResponsiveGauge from './ResponsiveGauge';

interface Props {
  data?: LineChartData;
  thresholdTooltipLabels: Array<string>;
  thresholds: Array<number>;
}

export const Gauge = ({
  thresholds,
  data,
  thresholdTooltipLabels
}: Props): JSX.Element | null => {
  if (!data) {
    return null;
  }
  const sortedThresholds = sort(subtract, thresholds);

  const metric = getMetricWithLatestData(data) as Metric;

  return (
    <Responsive.ParentSize>
      {({ width, height }) => (
        <ResponsiveGauge
          height={height}
          metric={metric}
          thresholdTooltipLabels={thresholdTooltipLabels}
          thresholds={sortedThresholds}
          width={width}
        />
      )}
    </Responsive.ParentSize>
  );
};
