import { Shape } from '@visx/visx';
import { isEmpty, isNil, prop } from 'ramda';
import { ScaleLinear } from 'd3-scale';

import { getTime } from '../../../timeSeries/index';
import { TimeValue } from '../../../timeSeries/models';

import usePointsOnline from './usePointsOnline';
import { Data } from './models';

interface Circle {
  dataY0: Data;
  dataY1: Data;
  dataYOrigin: Data;
  timeValue: TimeValue;
  variation: number;
  xScale: ScaleLinear<number, number>;
}

const Circle = ({
  timeValue,
  dataY0,
  dataY1,
  dataYOrigin,
  xScale,
  variation
}: Circle): JSX.Element | null => {
  const { metric: metricY0, yScale: y0Scale } = dataY0;
  const { metric: metricY1, yScale: y1Scale } = dataY1;
  const { metric: metricYOrigin, yScale } = dataYOrigin;

  const x = xScale(getTime(timeValue));
  const y = yScale(prop(metricYOrigin, timeValue));

  const y0 = y0Scale(prop(metricY0, timeValue) + variation);

  const y1 = y1Scale(prop(metricY1, timeValue) - variation);
  const coordinate = usePointsOnline({
    pointLower: { x, y: y0 },
    pointOrigin: { x, y },
    pointUpper: { x, y: y1 }
  });

  if (isEmpty(coordinate) || isNil(coordinate)) {
    return null;
  }

  return <Shape.Circle cx={coordinate.x} cy={coordinate.y} fill="red" r={2} />;
};

export default Circle;
