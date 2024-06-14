import * as Curve from '@visx/curve';
import { always, cond, equals, isNil } from 'ramda';

import { alpha } from '@mui/material';

const commonTickLabelProps = {
  fontFamily: 'Roboto, sans-serif',
  fontSize: 10,
  textAnchor: 'middle'
};

const margin = { bottom: 30, left: 50, right: 50, top: 30 };

interface FillColor {
  areaColor: string;
  transparency: number;
}

const getFillColor = ({
  transparency,
  areaColor
}: FillColor): string | undefined => {
  return !isNil(transparency)
    ? alpha(areaColor, 1 - transparency * 0.01)
    : undefined;
};

const dateFormat = 'L';
const timeFormat = 'LT';
const dateTimeFormat = `${dateFormat} ${timeFormat}`;
const maxLinesDisplayedLegend = 11;

const getCurveFactory = (
  curve: 'linear' | 'step' | 'natural'
): typeof Curve.curveLinear => {
  return cond([
    [equals('linear'), always(Curve.curveLinear)],
    [equals('step'), always(Curve.curveStep)],
    [equals('natural'), always(Curve.curveCatmullRom)]
  ])(curve);
};

export {
  commonTickLabelProps,
  margin,
  getFillColor,
  dateFormat,
  timeFormat,
  dateTimeFormat,
  maxLinesDisplayedLegend,
  getCurveFactory
};
