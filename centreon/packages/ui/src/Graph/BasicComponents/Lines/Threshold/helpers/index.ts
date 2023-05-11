import { isNil, prop } from 'ramda';
import { ScaleLinear } from 'd3-scale';

import {
  ArePointsOnline,
  Data,
  FactorsVariation,
  GetEnvelopeVariation,
  Online,
  Point,
  Result
} from '../models';
import { TimeValue } from '../../../../timeSeries/models';
import { getTime } from '../../../../timeSeries/index';

export const checkArePointsOnline = ({
  pointOrigin,
  pointUpper,
  pointLower
}: ArePointsOnline): Point | null => {
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

interface Props {
  dataY0: Data;
  dataY1: Data;
  factors: FactorsVariation;
  xScale: ScaleLinear<number, number>;
}

export const getVariationEnvelopThresholdData = ({
  dataY0,
  dataY1,
  factors,
  xScale
}: Props): Result => {
  const { lineColor: lineColorY0, metric: metricY0, yScale: y0Scale } = dataY0;
  const { lineColor: lineColorY1, metric: metricY1, yScale: y1Scale } = dataY1;

  const getEnvelopeVariation = ({
    metricUpper,
    metricLower,
    timeValue,
    factorsData
  }: GetEnvelopeVariation): number => {
    const upperRealValue = prop(metricUpper, timeValue);
    const lowerRealValue = prop(metricLower, timeValue);

    const { currentFactorMultiplication, simulatedFactorMultiplication } =
      factorsData;

    const variation = upperRealValue - lowerRealValue;

    const factor =
      1 - simulatedFactorMultiplication / currentFactorMultiplication;

    return (variation / 2) * factor;
  };

  const getX = (timeValue: TimeValue): number =>
    xScale(getTime(timeValue)) as number;

  const getY0 = (timeValue: TimeValue): number => {
    const variation = getEnvelopeVariation({
      factorsData: factors,
      metricLower: metricY0,
      metricUpper: metricY1,
      timeValue
    });

    return y0Scale(prop(metricY0, timeValue) + variation) ?? null;
  };
  const getY1 = (timeValue: TimeValue): number => {
    const variation = getEnvelopeVariation({
      factorsData: factors,
      metricLower: metricY0,
      metricUpper: metricY1,
      timeValue
    });

    return y1Scale(prop(metricY1, timeValue) - variation) ?? null;
  };

  return { getX, getY0, getY1, lineColorY0, lineColorY1 };
};
