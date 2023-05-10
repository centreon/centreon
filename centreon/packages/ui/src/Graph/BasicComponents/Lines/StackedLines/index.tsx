import { ReactNode } from 'react';

import { Curve, Shape } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import { all, isNil, map, not, nth, path, pipe, prop } from 'ramda';

import { StackedAnchorPoint } from '../../../IntercatifsComponents/AnchorPoint/models';
import { getFillColor } from '../../../common';
import { getTime } from '../../../timeSeries';
import { Line, TimeValue } from '../../../timeSeries/models';

interface Props {
  lines: Array<Line>;
  renderStackedAnchorPoint: (args: StackedAnchorPoint) => ReactNode;
  timeSeries: Array<TimeValue>;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

const StackLines = ({
  timeSeries,
  lines,
  yScale,
  xScale,
  renderStackedAnchorPoint
}: Props): JSX.Element => {
  return (
    <Shape.AreaStack
      curve={Curve.curveLinear}
      data={timeSeries}
      defined={(d): boolean => {
        return pipe(
          map(prop('metric')) as (displayedLines) => Array<string>,
          all((metric) => pipe(path(['data', metric]), isNil, not)(d))
        )(lines);
      }}
      keys={map(prop('metric'), lines)}
      x={(d): number => xScale(getTime(d.data)) ?? 0}
      y0={(d): number => yScale(d[0]) ?? 0}
      y1={(d): number => yScale(d[1]) ?? 0}
    >
      {({ stacks, path: linePath }): Array<JSX.Element> => {
        return stacks.map((stack, index) => {
          const { areaColor, transparency, lineColor, highlight } = nth(
            index,
            lines
          ) as Line;

          return (
            <g key={`stack-${prop('key', stack)}`}>
              {renderStackedAnchorPoint?.({
                areaColor,
                lineColor,
                stack,
                transparency,
                xScale,
                yScale
              })}
              <path
                d={linePath(stack) || ''}
                fill={getFillColor({ areaColor, transparency })}
                opacity={highlight === false ? 0.3 : 1}
                stroke={lineColor}
                strokeWidth={highlight ? 2 : 1}
              />
            </g>
          );
        });
      }}
    </Shape.AreaStack>
  );
};

export default StackLines;
