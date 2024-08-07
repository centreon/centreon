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
import { getDates, getYScale } from '../../../common/timeSeries';
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
  lineWidth?: number;
  showArea?: boolean;
  showPoints?: boolean;
  timeSeries: Array<TimeValue>;
  width: number;
  xScale: ScaleLinear<number, number>;
  yScalesPerUnit: Record<string, ScaleLinear<number, number>>;
}

const Lines = ({
  areaTransparency,
  height,
  graphSvgRef,
  width,
  displayAnchor,
  curve,
  yScalesPerUnit,
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
    xScale
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
              yScale={yScalesPerUnit[stackedLinesData.lines[0].unit]}
              {...commonStackedLinesProps}
            />
          )}

          {displayArea(invertedStackedLinesData.lines) && (
            <StackedLines
              lines={invertedStackedLinesData.lines}
              timeSeries={invertedStackedLinesData.timeSeries}
              yScale={yScalesPerUnit[invertedStackedLinesData.lines[0].unit]}
              {...commonStackedLinesProps}
            />
          )}
        </>
      )}

      {displayThresholdArea && (
        <WrapperThresholdLines
          areaThresholdLines={areaThresholdLines}
          graphHeight={height}
          lines={displayedLines}
          timeSeries={timeSeries}
          xScale={xScale}
          yScalesPerUnit={yScalesPerUnit}
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
              const yScale = getYScale({
                invert,
                unit,
                yScalesPerUnit
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
