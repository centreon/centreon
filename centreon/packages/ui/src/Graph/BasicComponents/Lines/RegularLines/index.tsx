import { memo } from 'react';

import { Shape } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import { equals, isNil, prop } from 'ramda';

import { getTime } from '../../../timeSeries';
import { TimeValue } from '../../../timeSeries/models';
import { getFillColor } from '../../../common';
import { CurveType } from '../models';

interface Props {
  areaColor: string;
  curve: CurveType;
  filled: boolean;
  graphHeight: number;
  highlight?: boolean;
  lineColor: string;
  metric: string;
  shapeAreaClosed?: Record<string, unknown>;
  shapeLinePath?: Record<string, unknown>;
  timeSeries: Array<TimeValue>;
  transparency: number;
  unit: string;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

const RegularLine = ({
  filled,
  timeSeries,
  highlight,
  metric,
  lineColor,
  unit,
  yScale,
  xScale,
  areaColor,
  transparency,
  graphHeight,
  curve
}: Props): JSX.Element => {
  const props = {
    curve,
    data: timeSeries,
    defined: (value): boolean => !isNil(value[metric]),
    opacity: 1,
    stroke: lineColor,
    strokeWidth: !highlight ? 1 : 2,
    unit,
    x: (timeValue): number => xScale(getTime(timeValue)) as number,
    y: (timeValue): number => yScale(prop(metric, timeValue)) ?? null
  };

  if (filled) {
    return (
      <Shape.AreaClosed<TimeValue>
        fill={getFillColor({ areaColor, transparency })}
        fillRule="nonzero"
        key={metric}
        y0={Math.min(yScale(0), graphHeight)}
        yScale={yScale}
        {...props}
      />
    );
  }

  return <Shape.LinePath<TimeValue> {...props} />;
};

export default memo(RegularLine, (prevProps, nextProps) => {
  const {
    timeSeries: prevTimeSeries,
    graphHeight: prevGraphHeight,
    highlight: prevHighlight,
    xScale: prevXScale
  } = prevProps;
  const {
    timeSeries: nextTimeSeries,
    graphHeight: nextGraphHeight,
    highlight: nextHighlight,
    xScale: nextXScale
  } = nextProps;

  const prevXScaleRange = prevXScale.range();
  const nextXScaleRange = nextXScale.range();

  return (
    equals(prevTimeSeries, nextTimeSeries) &&
    equals(prevGraphHeight, nextGraphHeight) &&
    equals(prevHighlight, nextHighlight) &&
    equals(prevXScaleRange, nextXScaleRange)
  );
});
