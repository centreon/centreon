import { MutableRefObject } from 'react';

import { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';

import { getPointRadius } from '../../../common/utils';
import GuidingLines from '../../InteractiveComponents/AnchorPoint/GuidingLines';
import RegularAnchorPoint, {
  getYAnchorPoint
} from '../../InteractiveComponents/AnchorPoint/RegularAnchorPoint';
import { displayArea } from '../../helpers/index';
import { DisplayAnchor, GlobalAreaLines } from '../../models';
import {
  getDates,
  getStackedYScale,
  getUnits,
  getYScale
} from '../../../common/timeSeries';
import { Line, TimeValue } from '../../../common/timeSeries/models';

import RegularLine from './RegularLines';
import useRegularLines from './RegularLines/useRegularLines';
import StackedLines from './StackedLines';
import useStackedLines from './StackedLines/useStackedLines';
import WrapperThresholdLines from './Threshold';
import {
  canDisplayThreshold,
  requiredNumberLinesThreshold
} from './Threshold/models';
import Point from './Point';

interface Props extends GlobalAreaLines {
  areaTransparency?: number;
  curve: 'linear' | 'step' | 'natural';
  dashLength?: number;
  dashOffset?: number;
  displayAnchor?: DisplayAnchor;
  displayedLines: Array<Line>;
  dotOffset?: number;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  height: number;
  leftScale: ScaleLinear<number, number>;
  lineWidth?: number;
  rightScale: ScaleLinear<number, number>;
  showArea?: boolean;
  showPoints?: boolean;
  timeSeries: Array<TimeValue>;
  width: number;
  xScale: ScaleLinear<number, number>;
}

const Lines = ({
  areaTransparency,
  height,
  graphSvgRef,
  width,
  displayAnchor,
  leftScale,
  rightScale,
  curve,
  xScale,
  timeSeries,
  displayedLines,
  areaThresholdLines,
  areaStackedLines,
  areaRegularLines,
  showArea,
  showPoints,
  lineWidth,
  dotOffset,
  dashLength,
  dashOffset
}: Props): JSX.Element => {
  const { stackedLinesData, invertedStackedLinesData } = useStackedLines({
    lines: displayedLines,
    timeSeries
  });

  const { regularLines } = useRegularLines({ lines: displayedLines });

  const displayThresholdArea =
    displayedLines?.length >= requiredNumberLinesThreshold &&
    canDisplayThreshold(areaThresholdLines);

  const displayAreaRegularLines =
    (areaRegularLines?.display ?? true) && displayArea(regularLines);

  const stackedYScale = getStackedYScale({
    leftScale,
    rightScale
  });

  const displayGuidingLines = displayAnchor?.displayGuidingLines ?? true;
  const commonStackedLinesProps = {
    areaTransparency,
    curve,
    dashLength,
    dashOffset,
    displayAnchor: displayGuidingLines,
    dotOffset,
    graphHeight: height,
    graphSvgRef,
    graphWidth: width,
    lineWidth,
    showArea,
    showPoints,
    xScale,
    yScale: stackedYScale
  };

  return (
    <g>
      {displayGuidingLines && (
        <GuidingLines
          graphHeight={height}
          graphWidth={width}
          timeSeries={timeSeries}
          xScale={xScale}
        />
      )}

      {(areaStackedLines?.display ?? true) && (
        <>
          {displayArea(stackedLinesData.lines) && (
            <StackedLines
              lines={stackedLinesData.lines}
              timeSeries={stackedLinesData.timeSeries}
              {...commonStackedLinesProps}
            />
          )}

          {displayArea(invertedStackedLinesData.lines) && (
            <StackedLines
              lines={invertedStackedLinesData.lines}
              timeSeries={invertedStackedLinesData.timeSeries}
              {...commonStackedLinesProps}
            />
          )}
        </>
      )}

      {displayThresholdArea && (
        <WrapperThresholdLines
          areaThresholdLines={areaThresholdLines}
          graphHeight={height}
          leftScale={leftScale}
          lines={displayedLines}
          rightScale={rightScale}
          timeSeries={timeSeries}
          xScale={xScale}
        />
      )}

      {displayAreaRegularLines
        ? regularLines.map(
            ({
              areaColor,
              transparency,
              lineColor,
              filled,
              unit,
              highlight,
              invert,
              metric_id
            }) => {
              const [, secondUnit, thirdUnit] = getUnits(
                regularLines as Array<Line>
              );
              const yScale = getYScale({
                hasMoreThanTwoUnits: !isNil(thirdUnit),
                invert,
                leftScale,
                rightScale,
                secondUnit,
                unit
              });

              return (
                <g key={metric_id}>
                  {displayGuidingLines && (
                    <RegularAnchorPoint
                      areaColor={areaColor || lineColor}
                      lineColor={lineColor}
                      metric_id={metric_id}
                      timeSeries={timeSeries}
                      transparency={transparency}
                      xScale={xScale}
                      yScale={yScale}
                    />
                  )}
                  {showPoints &&
                    getDates(timeSeries).map((timeTick) => (
                      <Point
                        key={timeTick.toString()}
                        lineColor={lineColor}
                        metric_id={metric_id}
                        radius={getPointRadius(lineWidth)}
                        timeSeries={timeSeries}
                        timeTick={timeTick}
                        xScale={xScale}
                        yPoint={getYAnchorPoint({
                          metric_id,
                          timeSeries,
                          timeTick,
                          yScale
                        })}
                        yScale={yScale}
                      />
                    ))}
                  <RegularLine
                    areaColor={areaColor || lineColor}
                    curve={curve}
                    dashLength={dashLength}
                    dashOffset={dashOffset}
                    dotOffset={dotOffset}
                    filled={isNil(showArea) ? filled : showArea}
                    graphHeight={height}
                    highlight={highlight}
                    lineColor={lineColor}
                    lineWidth={lineWidth}
                    metric_id={metric_id}
                    timeSeries={timeSeries}
                    transparency={
                      isNil(areaTransparency)
                        ? transparency || 80
                        : areaTransparency
                    }
                    unit={unit}
                    xScale={xScale}
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
