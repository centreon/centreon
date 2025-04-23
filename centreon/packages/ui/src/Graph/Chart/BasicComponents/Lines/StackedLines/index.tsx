import { Shape } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import {
  path,
  all,
  equals,
  isNil,
  map,
  not,
  nth,
  pipe,
  prop,
  type
} from 'ramda';

import { getDates, getTime } from '../../../../common/timeSeries';
import { Line, TimeValue } from '../../../../common/timeSeries/models';
import {
  getPointRadius,
  getStrokeDashArray,
  getStyle
} from '../../../../common/utils';
import StackedAnchorPoint, {
  getYAnchorPoint
} from '../../../InteractiveComponents/AnchorPoint/StackedAnchorPoint';
import { StackValue } from '../../../InteractiveComponents/AnchorPoint/models';
import { getCurveFactory, getFillColor } from '../../../common';
import { LineStyle } from '../../../models';
import Point from '../Point';

interface Props {
  areaTransparency?: number;
  curve: 'linear' | 'step' | 'natural';
  dashLength?: number;
  dashOffset?: number;
  displayAnchor: boolean;
  dotOffset?: number;
  lineWidth?: number;
  lines: Array<Line>;
  showArea?: boolean;
  showPoints?: boolean;
  timeSeries: Array<TimeValue>;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
  lineStyle: LineStyle | Array<LineStyle>;
}

const StackLines = ({
  timeSeries,
  lines,
  yScale,
  xScale,
  displayAnchor,
  lineStyle
}: Props): JSX.Element => {
  const curveType = getCurveFactory(
    (equals(type(lineStyle), 'Array')
      ? lineStyle?.[0].curve
      : lineStyle?.curve) || 'linear'
  );
  return (
    <Shape.AreaStack
      curve={curveType}
      data={timeSeries}
      defined={(d): boolean => {
        return pipe(
          map(prop('metric_id')) as unknown as (
            displayedLines
          ) => Array<string>,
          all((metric_id) => pipe(path(['data', metric_id]), isNil, not)(d))
        )(lines);
      }}
      keys={map(prop('metric_id'), lines)}
      x={(d): number => xScale(getTime(d.data)) ?? 0}
      y0={(d): number => yScale(d[0]) ?? 0}
      y1={(d): number => yScale(d[1]) ?? 0}
    >
      {({ stacks, path: linePath }): Array<JSX.Element> => {
        return stacks.map((stack, index) => {
          const { areaColor, transparency, lineColor, highlight, metric_id } =
            nth(index, lines) as Line;

          const style = getStyle({
            style: lineStyle,
            metricId: metric_id
          }) as LineStyle;
          const formattedLineWidth = style?.lineWidth ?? 2;

          const formattedTransparency = isNil(style?.areaTransparency)
            ? transparency || 80
            : style.areaTransparency;

          return (
            <g key={`stack-${prop('key', stack)}`}>
              {displayAnchor && (
                <StackedAnchorPoint
                  areaColor={style?.areaColor}
                  lineColor={lineColor}
                  stackValues={stack as unknown as Array<StackValue>}
                  timeSeries={timeSeries}
                  transparency={transparency}
                  xScale={xScale}
                  yScale={yScale}
                />
              )}
              {style?.showPoints &&
                getDates(timeSeries).map((timeTick) => (
                  <Point
                    key={timeTick.toString()}
                    lineColor={lineColor}
                    metric_id={metric_id}
                    radius={getPointRadius(style?.lineWidth)}
                    timeSeries={timeSeries}
                    timeTick={timeTick}
                    xScale={xScale}
                    yPoint={getYAnchorPoint({
                      stackValues: stack as unknown as Array<StackValue>,
                      timeTick,
                      yScale
                    })}
                    yScale={yScale}
                  />
                ))}
              <path
                d={linePath(stack) || ''}
                data-metric={metric_id}
                fill={
                  equals(style?.showArea, false)
                    ? 'transparent'
                    : getFillColor({
                        areaColor: areaColor || lineColor,
                        transparency: formattedTransparency
                      })
                }
                opacity={highlight === false ? 0.3 : 1}
                stroke={lineColor}
                strokeDasharray={getStrokeDashArray({
                  dashLength: style?.dashLength,
                  dashOffset: style?.dashOffset,
                  dotOffset: style?.dotOffset,
                  lineWidth: style?.lineWidth ?? 2
                })}
                strokeWidth={
                  highlight
                    ? Math.ceil(formattedLineWidth * 1.3)
                    : formattedLineWidth
                }
              />
            </g>
          );
        });
      }}
    </Shape.AreaStack>
  );
};

export default StackLines;
