import { ParentSize } from '../..';
import { LineChartData, Thresholds } from '../common/models';
import { getMetricWithLatestData } from '../common/timeSeries';
import { Metric } from '../common/timeSeries/models';

import ResponsiveGauge from './ResponsiveGauge';

export interface Props {
  baseColor?: string;
  data?: LineChartData;
  displayAsRaw?: boolean;
  thresholds: Thresholds;
}

export const Gauge = ({
  thresholds,
  data,
  displayAsRaw,
  baseColor
}: Props): JSX.Element | null => {
  if (!data) {
    return null;
  }

  const metric = getMetricWithLatestData(data) as Metric;

  return (
    <ParentSize>
      {({ width, height }) => (
        <ResponsiveGauge
          baseColor={baseColor}
          displayAsRaw={displayAsRaw}
          height={height}
          metric={metric}
          thresholds={thresholds}
          width={width}
        />
      )}
    </ParentSize>
  );
};
