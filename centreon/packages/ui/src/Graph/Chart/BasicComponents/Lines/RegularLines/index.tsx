import { memo } from 'react';

import { Shape } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import { equals, isNil, pick, prop } from 'ramda';

import { getTime } from '../../../../common/timeSeries';
import { TimeValue } from '../../../../common/timeSeries/models';
import { getStrokeDashArray } from '../../../../common/utils';
import { getCurveFactory, getFillColor } from '../../../common';

interface Props {
  areaColor: string;
  curve: 'linear' | 'step' | 'natural';
  dashLength?: number;
  dashOffset?: number;
  dotOffset?: number;
  filled: boolean;
  graphHeight: number;
  highlight?: boolean;
  lineColor: string;
  lineWidth?: number;
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
  curve,
  lineWidth,
  dotOffset,
  dashLength,
  dashOffset
}: Props): JSX.Element => {
  const curveType = getCurveFactory(curve);
  const formattedLineWidth = lineWidth ?? 2;

  const props = {
    curve: curveType,
    data: timeSeries,
    defined: (value): boolean => !isNil(value[metric_id]),
    opacity: 1,
    stroke: lineColor,
    strokeDasharray: getStrokeDashArray({
      dashLength,
      dashOffset,
      dotOffset,
      lineWidth: formattedLineWidth
    }),
    strokeWidth: highlight
      ? Math.ceil((formattedLineWidth || 1) * 1.3)
      : formattedLineWidth,
    unit,
    x: (timeValue): number => xScale(getTime(timeValue)) as number,
    y: (timeValue): number => yScale(prop(metric_id, timeValue)) ?? null
  };

  if (filled) {
    return (
      <Shape.AreaClosed<TimeValue>
        data-metric={metric_id}
        fill={getFillColor({ areaColor, transparency })}
        fillRule="nonzero"
        key={metric_id}
        y0={Math.min(yScale(0), graphHeight)}
        yScale={yScale}
        {...props}
      />
    );
  }

  return <Shape.LinePath<TimeValue> data-metric={metric_id} {...props} />;
};

const memoizedProps = [
  'curve',
  'lineColor',
  'areaColor',
  'filled',
  'transparency',
  'lineWidth',
  'dotOffset',
  'dashLength',
  'dashOffset'
];

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
    equals(prevYScaleDomain, nextYScaleDomain) &&
    equals(pick(memoizedProps, prevProps), pick(memoizedProps, nextProps))
  );
});
