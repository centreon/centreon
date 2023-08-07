import { MutableRefObject } from 'react';

import { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';

import GuidingLines from '../../InteractiveComponents/AnchorPoint/GuidingLines';
import RegularAnchorPoint from '../../InteractiveComponents/AnchorPoint/RegularAnchorPoint';
import { displayArea } from '../../helpers/index';
import { DisplayAnchor, GlobalAreaLines } from '../../models';
import { getStackedYScale, getUnits, getYScale } from '../../timeSeries';
import { Line, TimeValue } from '../../timeSeries/models';

import RegularLine from './RegularLines';
import useRegularLines from './RegularLines/useRegularLines';
import StackedLines from './StackedLines';
import useStackedLines from './StackedLines/useStackedLines';
import WrapperThresholdLines from './Threshold';
import {
  canDisplayThreshold,
  requiredNumberLinesThreshold
} from './Threshold/models';

interface Props extends GlobalAreaLines {
  displayAnchor?: DisplayAnchor;
  displayedLines: Array<Line>;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  height: number;
  leftScale: ScaleLinear<number, number>;
  rightScale: ScaleLinear<number, number>;
  timeSeries: Array<TimeValue>;
  width: number;
  xScale: ScaleLinear<number, number>;
}

const Lines = ({
  height,
  graphSvgRef,
  width,
  displayAnchor,
  leftScale,
  rightScale,
  xScale,
  timeSeries,
  displayedLines,
  areaThresholdLines,
  areaStackedLines,
  areaRegularLines
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
    displayAnchor: displayGuidingLines,
    graphHeight: height,
    graphSvgRef,
    graphWidth: width,
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
                leftScale,
                rightScale,
                secondUnit,
                unit
              });

              return (
                <g key={metric}>
                  {displayGuidingLines && (
                    <RegularAnchorPoint
                      areaColor={areaColor}
                      lineColor={lineColor}
                      metric={metric}
                      timeSeries={timeSeries}
                      transparency={transparency}
                      xScale={xScale}
                      yScale={yScale}
                    />
                  )}
                  <RegularLine
                    areaColor={areaColor}
                    filled={filled}
                    graphHeight={height}
                    highlight={highlight}
                    lineColor={lineColor}
                    metric={metric}
                    timeSeries={timeSeries}
                    transparency={transparency}
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
