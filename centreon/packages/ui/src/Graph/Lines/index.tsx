import { isEmpty, isNil } from 'ramda';

import { getStackedYScale, getUnits, getYScale } from '../../timeSeries';
import { Line, TimeValue } from '../../timeSeries/models';
import { shapeGraphData } from '../models';

import RegularLine from './RegularLines';
import StackedLines from './StackedLines';

interface Shape {
  areaRegularLines?: shapeGraphData;
  areaStackedLines?: shapeGraphData;
}

interface Props {
  height: number;
  shape: Shape;
}

const Lines = ({ height, shape }: Props): JSX.Element => {
  const displayAreaStackedLines =
    !isEmpty(shape?.areaStackedLines) && !isNil(shape?.areaStackedLines);

  const displayRegularLines =
    !isEmpty(shape?.areaRegularLines) && !isNil(shape?.areaRegularLines);

  const lineStackedLines = shape?.areaStackedLines?.lines;
  const timeSeriesStackedLines = shape?.areaStackedLines?.timeSeries;
  const leftScaleStackedLines = shape?.areaStackedLines?.leftScale;
  const rightScaleStackedLines = shape?.areaStackedLines?.rightScale;
  const xScaleStackedLines = shape?.areaRegularLines?.xScale;

  const stackedYScale = getStackedYScale({
    leftScale: leftScaleStackedLines,
    rightScale: rightScaleStackedLines
  });

  const linesRegularLines = shape?.areaRegularLines?.lines;
  const timeSeriesRegularLines = shape?.areaRegularLines?.timeSeries;
  const leftScaleRegularLines = shape?.areaRegularLines?.leftScale;
  const rightScaleRegularLines = shape?.areaRegularLines?.rightScale;
  const xScaleRegularLines = shape?.areaRegularLines?.xScale;

  return (
    <g>
      {displayAreaStackedLines && (
        <StackedLines
          lines={lineStackedLines as Array<Line>}
          timeSeries={timeSeriesStackedLines as Array<TimeValue>}
          timeTick={shape?.areaStackedLines?.timeTick as Date}
          xScale={xScaleStackedLines}
          yScale={stackedYScale}
          {...shape?.areaStackedLines}
        />
      )}

      {displayRegularLines
        ? linesRegularLines?.map(
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
              const [, secondUnit, thirdUnit] = getUnits(
                linesRegularLines as Array<Line>
              );
              const yScale = getYScale({
                hasMoreThanTwoUnits: !isNil(thirdUnit),
                invert,
                leftScale: leftScaleRegularLines,
                rightScale: rightScaleRegularLines,
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
                    timeSeries={timeSeriesRegularLines as Array<TimeValue>}
                    transparency={transparency}
                    unit={unit}
                    xScale={xScaleRegularLines}
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
