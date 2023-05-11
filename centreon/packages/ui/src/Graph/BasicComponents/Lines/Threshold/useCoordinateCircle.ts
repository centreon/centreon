import { useEffect } from 'react';

import { isNil, prop } from 'ramda';

import {
  checkArePointsOnline,
  getVariationEnvelopThresholdData
} from './helpers';
import { Circle, Point } from './models';

const useCoordinateCircle = ({
  timeSeries,
  dataYOrigin,
  dataY0,
  dataY1,
  factors,
  xScale,
  getCountDisplayedCircles
}: Circle): Array<Point> => {
  const { metric: metricYOrigin, yScale } = dataYOrigin;

  const getCoordinate = (): Array<Point | null> => {
    const { getX, getY0, getY1 } = getVariationEnvelopThresholdData({
      dataY0,
      dataY1,
      factors,
      xScale
    });

    return timeSeries.map((timeValue) => {
      const x = getX(timeValue);
      const y = yScale(prop(metricYOrigin, timeValue));

      const y0 = getY0(timeValue);
      const y1 = getY1(timeValue);

      return checkArePointsOnline({
        pointLower: { x, y: y0 },
        pointOrigin: { x, y },
        pointUpper: { x, y: y1 }
      });
    });
  };

  const coordinates = getCoordinate()?.filter(
    (element) => !isNil(element)
  ) as Array<Point>;

  useEffect(() => {
    if (!coordinates) {
      return;
    }
    getCountDisplayedCircles?.(coordinates.length);
  }, [coordinates.length]);

  return coordinates;
};

export default useCoordinateCircle;
