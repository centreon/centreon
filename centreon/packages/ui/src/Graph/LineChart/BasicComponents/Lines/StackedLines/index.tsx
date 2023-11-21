import { Shape } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import { all, isNil, map, not, nth, path, pipe, prop } from 'ramda';

import StackedAnchorPoint from '../../../InteractiveComponents/AnchorPoint/StackedAnchorPoint';
import { StackValue } from '../../../InteractiveComponents/AnchorPoint/models';
import { getFillColor } from '../../../common';
import { getTime } from '../../../../common/timeSeries';
import { Line, TimeValue } from '../../../../common/timeSeries/models';
import { CurveType } from '../models';

interface Props {
  curve: CurveType;
  displayAnchor: boolean;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

const StackLines = ({
  timeSeries,
  lines,
  yScale,
  xScale,
  displayAnchor,
  curve
}: Props): JSX.Element => {
  return (
    <Shape.AreaStack
      curve={curve}
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
          const { areaColor, transparency, lineColor, highlight } = nth(
            index,
            lines
          ) as Line;

          return (
            <g key={`stack-${prop('key', stack)}`}>
              {displayAnchor && (
                <StackedAnchorPoint
                  areaColor={areaColor}
                  lineColor={lineColor}
                  stackValues={stack as unknown as Array<StackValue>}
                  timeSeries={timeSeries}
                  transparency={transparency}
                  xScale={xScale}
                  yScale={yScale}
                />
              )}
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
