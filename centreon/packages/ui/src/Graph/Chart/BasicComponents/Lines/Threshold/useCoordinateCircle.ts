import { useEffect } from 'react';

import { isNil } from 'ramda';

import { checkArePointsOnline } from './helpers';
import { Circle, Point } from './models';

const useCoordinateCircle = ({
  timeSeries,
  getX,
  getY0Variation,
  getY1Variation,
  getYOrigin,
  getCountDisplayedCircles
}: Circle): Array<Point> => {
  const getCoordinate = (): Array<Point | null> => {
    return timeSeries.map((timeValue) => {
      const x = getX(timeValue);
      const y = getYOrigin(timeValue);

      const y0 = getY0Variation(timeValue);
      const y1 = getY1Variation(timeValue);

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
