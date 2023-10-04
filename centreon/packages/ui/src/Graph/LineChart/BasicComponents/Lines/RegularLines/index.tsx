import { memo } from 'react';

import { Shape } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import { equals, isNil, prop } from 'ramda';

import { getTime } from '../../../../common/timeSeries';
import { TimeValue } from '../../../../common/timeSeries/models';
import { getFillColor } from '../../../common';
import { CurveType } from '../models';

interface Props {
  areaColor: string;
  curve: CurveType;
  filled: boolean;
  graphHeight: number;
  highlight?: boolean;
  lineColor: string;
  metric_id: number;
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
  metric_id,
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
    defined: (value): boolean => !isNil(value[metric_id]),
    opacity: 1,
    stroke: lineColor,
    strokeWidth: !highlight ? 1 : 2,
    unit,
    x: (timeValue): number => xScale(getTime(timeValue)) as number,
    y: (timeValue): number => yScale(prop(metric_id, timeValue)) ?? null
  };

  if (filled) {
    return (
      <Shape.AreaClosed<TimeValue>
        fill={getFillColor({ areaColor, transparency })}
        fillRule="nonzero"
        key={metric_id}
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
    xScale: prevXScale,
    yScale: prevYScale
  } = prevProps;
  const {
    timeSeries: nextTimeSeries,
    graphHeight: nextGraphHeight,
    highlight: nextHighlight,
    xScale: nextXScale,
    yScale: nextYScale
  } = nextProps;

  const prevXScaleRange = prevXScale.range();
  const nextXScaleRange = nextXScale.range();
  const prevYScaleDomain = prevYScale.domain();
  const nextYScaleDomain = nextYScale.domain();

  return (
    equals(prevTimeSeries, nextTimeSeries) &&
    equals(prevGraphHeight, nextGraphHeight) &&
    equals(prevHighlight, nextHighlight) &&
    equals(prevXScaleRange, nextXScaleRange) &&
    equals(prevYScaleDomain, nextYScaleDomain)
  );
});
