import { Shape } from '@visx/visx';
import { isEmpty, isNil } from 'ramda';

import { Circle } from './models';
import useCoordinateCircle from './useCoordinateCircle';

const Circle = ({
  dataY0,
  dataY1,
  dataYOrigin,
  xScale,
  factors,
  timeSeries,
  getCountDisplayedCircles
}: Circle): JSX.Element | null => {
  const coordinates = useCoordinateCircle({
    dataY0,
    dataY1,
    dataYOrigin,
    factors,
    getCountDisplayedCircles,
    timeSeries,
    xScale
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
