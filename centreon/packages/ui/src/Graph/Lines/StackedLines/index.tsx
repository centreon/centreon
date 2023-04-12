import { map, nth, pipe, path, all, not, isNil, prop } from 'ramda';
import { Shape, Curve } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';

// import StackedAnchorPoint, {
//   StackValue
// } from './AnchorPoint/StackedAnchorPoint';

import { Line, TimeValue } from '../../../timeSeries/models';
import { getTime } from '../../../timeSeries';
import { getFillColor } from '../../common';

interface Props {
  displayTimeValues?: boolean;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  timeTick: Date | null;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

const StackLines = ({
  timeSeries,
  lines,
  yScale,
  xScale,
  timeTick,
  displayTimeValues,
  ...rest
}: Props): JSX.Element => (
  <Shape.AreaStack
    curve={Curve.curveLinear}
    data={timeSeries}
    defined={(d): boolean => {
      return pipe(
        map(prop('metric')) as (lines) => Array<string>,
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
            {/* <StackedAnchorPoint
              areaColor={areaColor}
              displayTimeValues={displayTimeValues}
              lineColor={lineColor}
              stackValues={stack as unknown as Array<StackValue>}
              timeTick={timeTick}
              transparency={transparency}
              xScale={xScale}
              yScale={yScale}
            /> */}
            <path
              d={linePath(stack) || ''}
              fill={getFillColor({ areaColor, transparency })}
              opacity={highlight === false ? 0.3 : 1}
              stroke={lineColor}
              strokeWidth={highlight ? 2 : 1}
              {...rest}
            />
          </g>
        );
      });
    }}
  </Shape.AreaStack>
);

export default StackLines;
