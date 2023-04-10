import { isEmpty, isNil } from 'ramda';
import { ScaleLinear, ScaleTime } from 'd3-scale';

import { Line, TimeValue } from '../../timeSeries/models';
import { getUnits, getYScale } from '../../timeSeries';

import StackedLines from './stackedLines';
import RegularLine from './regularLines';

interface AreaStack {
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  timeTick: Date | null;
  yScale: ScaleLinear<number, number>;
}

interface AreaRegularLines {
  displayAreaRegularLines: boolean;
  lines: Array<Line>;
}

interface Shape {
  areaRegularLines?: AreaRegularLines;
  areaStack?: AreaStack;
  threshold?: any;
}

interface Props {
  data: Pick<AreaStack, 'lines' | 'timeSeries'>;
  height: number;
  leftScale: any;
  rightScale: any;
  shape: Shape;
  xScale: any;
}

const Lines = ({
  xScale,
  height,
  shape,
  data,
  leftScale,
  rightScale
}: Props): JSX.Element => {
  const { timeSeries, lines } = data;
  const [, secondUnit, thirdUnit] = getUnits(lines);

  const displayAreaStack =
    !isEmpty(shape?.areaStack) && !isNil(shape?.areaStack);

  const displayRegularLines =
    !isEmpty(shape?.areaRegularLines) &&
    !isNil(shape?.areaRegularLines) &&
    shape?.areaRegularLines?.displayAreaRegularLines;

  console.log({
    dis: shape?.areaRegularLines?.displayAreaRegularLines,
    displayRegularLines
  });

  return (
    <g>
      {displayAreaStack && (
        <StackedLines
          lines={shape?.areaStack?.lines as Array<Line>}
          timeSeries={shape?.areaStack?.timeSeries as Array<TimeValue>}
          timeTick={shape?.areaStack?.timeTick as Date}
          xScale={xScale}
          yScale={shape?.areaStack?.yScale as ScaleLinear<number, number>}
        />
      )}

      {displayRegularLines
        ? shape?.areaRegularLines?.lines.map(
            ({
              metric,
              areaColor,
              transparency,
              lineColor,
              filled,
              unit,
              highlight,
              invert
            }) => {
              const yScale = getYScale({
                hasMoreThanTwoUnits: !isNil(thirdUnit),
                invert,
                leftScale,
                rightScale,
                secondUnit,
                unit
              });

              return (
                <g key={metric}>
                  {/* <RegularAnchorPoint
                areaColor={areaColor}
                displayTimeValues={displayTimeValues}
                lineColor={lineColor}
                metric={metric}
                timeSeries={timeSeries}
                timeTick={timeTick}
                transparency={transparency}
                xScale={xScale}
                yScale={yScale}
              /> */}
                  <RegularLine
                    areaColor={areaColor}
                    filled={filled}
                    graphHeight={height}
                    highlight={highlight}
                    lineColor={lineColor}
                    metric={metric}
                    timeSeries={data.timeSeries}
                    transparency={transparency}
                    unit={unit}
                    xScale={xScale}
                    yScale={yScale}
                    {...shape?.areaRegularLines}
                  />
                </g>
              );
            }
          )
        : null}
    </g>
  );
};

export default Lines;
