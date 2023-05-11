import { ReactNode } from 'react';

import { isNil } from 'ramda';

import {
  RegularLinesAnchorPoint,
  StackedAnchorPoint
} from '../../IntercatifsComponents/AnchorPoint/models';
import { getUnits, getYScale } from '../../timeSeries';
import { Line } from '../../timeSeries/models';

import RegularLine from './RegularLines';
import useDataRegularLines from './RegularLines/useDataRegularLines';
import StackedLines from './StackedLines';
import useDataStackedLines from './StackedLines/useDataStackLines';
import ThresholdLines from './Threshold';
import AwesomeCircles from './Threshold/Circle';
import { Data } from './Threshold/models';
import useDataThreshold from './Threshold/useDataThreshold';
import { Shape } from './models';

interface AnchorPoint {
  renderRegularLinesAnchorPoint: (args: RegularLinesAnchorPoint) => ReactNode;
  renderStackedAnchorPoint: (args: StackedAnchorPoint) => ReactNode;
}

interface Props {
  anchorPoint: AnchorPoint;
  height: number;
  shape: Shape;
}

const Lines = ({ height, shape, anchorPoint }: Props): JSX.Element => {
  const { areaRegularLines, areaStackedLines, areaThreshold } = shape;
  const { renderRegularLinesAnchorPoint, renderStackedAnchorPoint } =
    anchorPoint;
  const { lines, leftScale, rightScale, timeSeries, xScale, display } =
    areaThreshold;

  const {
    displayAreaInvertedStackedLines,
    displayAreaStackedLines,
    invertedStackedLines,
    invertedStackedLinesTimeSeries,
    regularStackedLines,
    regularStackedLinesTimeSeries,
    xScaleStackedLines,
    yScaleStackedLines
  } = useDataStackedLines(areaStackedLines);

  const {
    display: displayAreaRegularLines,
    leftScale: leftScaleRegularLines,
    timeSeries: regularLinesTimeSeries,
    rightScale: rightScaleRegularLines,
    xScale: xScaleRegularLines,
    lines: regularLines
  } = useDataRegularLines(areaRegularLines);

  const { dataY0, dataY1, dataYOrigin, displayThreshold } = useDataThreshold({
    display,
    leftScale,
    lines,
    rightScale
  });

  const commonStackedLinesProps = { xScaleStackedLines, yScaleStackedLines };

  return (
    <g>
      {displayAreaStackedLines && (
        <StackedLines
          lines={regularStackedLines}
          renderStackedAnchorPoint={renderStackedAnchorPoint}
          timeSeries={regularStackedLinesTimeSeries}
          {...commonStackedLinesProps}
        />
      )}
      {displayAreaInvertedStackedLines && (
        <StackedLines
          lines={invertedStackedLines}
          renderStackedAnchorPoint={renderStackedAnchorPoint}
          timeSeries={invertedStackedLinesTimeSeries}
          {...commonStackedLinesProps}
        />
      )}

      {displayThreshold && (
        <>
          <ThresholdLines
            dataY0={dataY0 as Data}
            dataY1={dataY1 as Data}
            graphHeight={height}
            timeSeries={timeSeries}
            xScale={xScale}
          />
          <ThresholdLines
            dataY0={dataY0 as Data}
            dataY1={dataY1 as Data}
            graphHeight={height}
            timeSeries={timeSeries}
            variation={areaThreshold?.variation}
            xScale={xScale}
          />
          {areaThreshold?.variation &&
            timeSeries.map((timeValue) => (
              <AwesomeCircles
                dataY0={dataY0 as Data}
                dataY1={dataY1 as Data}
                dataYOrigin={dataYOrigin as Data}
                key={timeValue.timeTick}
                timeValue={timeValue}
                variation={areaThreshold?.variation as number}
                xScale={xScale}
              />
            ))}
        </>
      )}

      {displayAreaRegularLines
        ? regularLines.map(
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
                regularLines as Array<Line>
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
                  {renderRegularLinesAnchorPoint?.({
                    areaColor,
                    lineColor,
                    metric,
                    timeSeries: regularLinesTimeSeries,
                    transparency,
                    xScale: xScaleRegularLines,
                    yScale
                  })}
                  <RegularLine
                    areaColor={areaColor}
                    filled={filled}
                    graphHeight={height}
                    highlight={highlight}
                    lineColor={lineColor}
                    metric={metric}
                    timeSeries={regularLinesTimeSeries}
                    transparency={transparency}
                    unit={unit}
                    xScale={xScaleRegularLines}
                    yScale={yScale}
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
