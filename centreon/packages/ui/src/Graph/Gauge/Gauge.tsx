import { Responsive } from '@visx/visx';

import { LineChartData, Thresholds } from '../common/models';
import { getMetricWithLatestData } from '../common/timeSeries';
import { Metric } from '../common/timeSeries/models';

import ResponsiveGauge from './ResponsiveGauge';

interface Props {
  data?: LineChartData;
  thresholds: Thresholds;
}

export const Gauge = ({ thresholds, data }: Props): JSX.Element | null => {
  if (!data) {
    return null;
  }

  const metric = getMetricWithLatestData(data) as Metric;

  return (
    <Responsive.ParentSize>
      {({ width, height }) => (
        <ResponsiveGauge
          height={height}
          metric={metric}
          thresholds={thresholds}
          width={width}
        />
      )}
    </Responsive.ParentSize>
  );
};
