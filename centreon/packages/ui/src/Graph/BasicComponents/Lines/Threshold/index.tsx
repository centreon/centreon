import { curveBasis } from '@visx/curve';
import { Threshold } from '@visx/threshold';
import { ScaleLinear } from 'd3-scale';
import { prop } from 'ramda';

import { getTime } from '../../../timeSeries/index';
import { TimeValue } from '../../../timeSeries/models';

import { Data } from './models';

interface Props {
  dataY0: Data;
  dataY1: Data;
  graphHeight: number;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

const ThresholdLines = ({
  dataY0,
  dataY1,
  timeSeries,
  xScale,
  graphHeight
}: Props): JSX.Element => {
  const { lineColor: lineColorY0, metric: metricY0, yScale: y0Scale } = dataY0;
  const { lineColor: lineColorY1, metric: metricY1, yScale: y1Scale } = dataY1;

  return (
    <Threshold
      aboveAreaProps={{
        fill: lineColorY1,
        fillOpacity: 0.1
      }}
      belowAreaProps={{
        fill: lineColorY0,
        fillOpacity: 0.1
      }}
      clipAboveTo={0}
      clipBelowTo={graphHeight}
      curve={curveBasis}
      data={timeSeries}
      id="threshold"
      x={(timeValue: TimeValue): number => xScale(getTime(timeValue)) as number}
      y0={(timeValue): number => y0Scale(prop(metricY0, timeValue)) ?? null}
      y1={(timeValue): number => y1Scale(prop(metricY1, timeValue)) ?? null}
    />
  );
};

export default ThresholdLines;
