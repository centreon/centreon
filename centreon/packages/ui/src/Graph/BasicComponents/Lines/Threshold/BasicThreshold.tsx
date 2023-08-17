import { curveBasis } from '@visx/curve';
import { Threshold } from '@visx/threshold';

import { TimeValue } from '../../../timeSeries/models';

interface Props {
  fillAboveArea: string;
  fillBelowArea: string;
  fillOpacity?: number;
  getX: (timeValue: TimeValue) => number;
  getY0: (timeValue: TimeValue) => number;
  getY1: (timeValue: TimeValue) => number;
  graphHeight: number;
  id: string;
  timeSeries: Array<TimeValue>;
}

const BasicThreshold = ({
  getX,
  getY0,
  getY1,
  graphHeight,
  timeSeries,
  fillOpacity = 0.1,
  id,
  fillAboveArea,
  fillBelowArea
}: Props): JSX.Element => {
  return (
    <Threshold
      aboveAreaProps={{
        fill: fillAboveArea,
        fillOpacity
      }}
      belowAreaProps={{
        fill: fillBelowArea,
        fillOpacity
      }}
      clipAboveTo={0}
      clipBelowTo={graphHeight}
      curve={curveBasis}
      data={timeSeries}
      id={id}
      x={getX}
      y0={getY0}
      y1={getY1}
    />
  );
};

export default BasicThreshold;
