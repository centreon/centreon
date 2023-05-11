import { isNil } from 'ramda';

import { Online, Point, Props } from './models';

const usePointsOnline = ({
  pointOrigin,
  pointUpper,
  pointLower
}: Props): Point | null => {
  const isPointDefined = ({ x, y }: Point): boolean => !isNil(x) && !isNil(y);

  const arePointsDefined =
    isPointDefined(pointOrigin) &&
    isPointDefined(pointUpper) &&
    isPointDefined(pointLower);

  const isOnLine = ({
    pointOrigin: origin,
    pointLower: firstPoint,
    pointUpper: secondPoint,
    maxDistance = 0
  }: Online): boolean => {
    const { x, y } = origin;
    const { x: x1, y: y1 } = firstPoint;
    const { x: x2, y: y2 } = secondPoint;

    const dxL = x2 - x1;
    const dyL = y2 - y1;
    const dxP = x - x1;
    const dyP = y - y1;

    const squareLen = dxL * dxL + dyL * dyL;
    const dotProd = dxP * dxL + dyP * dyL;
    const crossProd = dyP * dxL - dxP * dyL;

    const distance = Math.abs(crossProd) / Math.sqrt(squareLen);

    return distance <= maxDistance && dotProd >= 0 && dotProd <= squareLen;
  };

  const arePointsOnline = isOnLine({
    pointLower,
    pointOrigin,
    pointUpper
  });

  return !arePointsOnline && arePointsDefined ? pointOrigin : null;
};

export default usePointsOnline;
