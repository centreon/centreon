import { Shape } from '@visx/visx';
import { isEmpty, isNil } from 'ramda';

import { Circle as CircleModel } from './models';
import useCoordinateCircle from './useCoordinateCircle';

const Circle = ({
  getY0Variation,
  getY1Variation,
  getYOrigin,
  timeSeries,
  getX,
  getCountDisplayedCircles
}: CircleModel): JSX.Element | null => {
  const coordinates = useCoordinateCircle({
    getCountDisplayedCircles,
    getX,
    getY0Variation,
    getY1Variation,
    getYOrigin,
    timeSeries
  });

  if (isEmpty(coordinates) || isNil(coordinates)) {
    return null;
  }

  return (
    <g>
      {coordinates.map(({ x, y }) => (
        <Shape.Circle
          cx={x}
          cy={y}
          fill="red"
          key={`${x.toString()}-${y.toString()}`}
          r={2}
        />
      ))}
    </g>
  );
};

export default Circle;
