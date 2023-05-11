import { curveBasis } from '@visx/curve';
import { ScaleLinear } from 'd3-scale';
import { Threshold } from '@visx/threshold';

import { TimeValue } from '../../../timeSeries/models';

interface Props {
  getX: ScaleLinear<number, number>;
  getY0: ScaleLinear<number, number>;
  getY1: ScaleLinear<number, number>;
  graphHeight: number;
  lineColorY0: string;
  lineColorY1: string;
  timeSeries: Array<TimeValue>;
}

const BasicThreshold = ({
  getX,
  getY0,
  getY1,
  lineColorY0,
  lineColorY1,
  graphHeight,
  timeSeries
}: Props): JSX.Element => {
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
      x={getX}
      y0={getY0}
      y1={getY1}
    />
  );
};

export default BasicThreshold;
