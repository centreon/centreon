import { MutableRefObject } from 'react';

import { ScaleLinear } from 'd3-scale';
import { isEmpty, isNil } from 'ramda';

import RegularAnchorPoint from '../../InteractiveComponents/AnchorPoint/RegularAnchorPoint';
import { displayArea } from '../../helpers/index';
import { GlobalAreaLines } from '../../models';
import { getStackedYScale, getUnits, getYScale } from '../../timeSeries';
import { Line, TimeValue } from '../../timeSeries/models';

import RegularLine from './RegularLines';
import useRegularLines from './RegularLines/useRegularLines';
import StackedLines from './StackedLines';
import useStackedLines from './StackedLines/useStackedLines';
import ThresholdLines from './Threshold';
import AwesomeCircles from './Threshold/Circle';
import ThresholdWithPatternLines from './Threshold/ThresholdWithPatternLines';
import ThresholdWithVariation from './Threshold/ThresholdWithVariation';
import { Data } from './Threshold/models';
import useDataThreshold from './Threshold/useDataThreshold';

interface Props extends GlobalAreaLines {
  displayAnchor: boolean;
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

  const displayEnvelopeThreshold =
    !isNil(areaThresholdLines?.factors?.currentFactorMultiplication) &&
    !isNil(areaThresholdLines?.factors?.simulatedFactorMultiplication);

  const currentFactorMultiplication = areaThresholdLines?.factors
    ?.currentFactorMultiplication as number;

  const simulatedFactorMultiplication = areaThresholdLines?.factors
    ?.simulatedFactorMultiplication as number;

  const getCountDisplayedCircles = areaThresholdLines?.getCountDisplayedCircles;

  const dataExclusionPeriods = areaThresholdLines?.dataExclusionPeriods;

  const { dataY0, dataY1, dataYOrigin, displayThreshold } = useDataThreshold({
    display: areaThresholdLines?.display ?? false,
    leftScale,
    lines: displayedLines,
    rightScale
  });

  const displayAreaRegularLines =
    (areaRegularLines?.display ?? true) && displayArea(regularLines);

  const stackedYScale = getStackedYScale({
    leftScale,
    rightScale
  });

  const commonStackedLinesProps = {
    displayAnchor,
    graphHeight: height,
    graphSvgRef,
    graphWidth: width,
    xScale,
    yScale: stackedYScale
  };

  return (
    <g>
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

      {displayThreshold && (
        <>
          <ThresholdLines
            dataY0={dataY0 as Data}
            dataY1={dataY1 as Data}
            graphHeight={height}
            timeSeries={timeSeries}
            xScale={xScale}
          />
          {displayEnvelopeThreshold && (
            <ThresholdWithVariation
              dataY0={dataY0 as Data}
              dataY1={dataY1 as Data}
              factors={{
                currentFactorMultiplication,
                simulatedFactorMultiplication
              }}
              graphHeight={height}
              timeSeries={timeSeries}
              xScale={xScale}
            />
          )}
          {displayEnvelopeThreshold && (
            <AwesomeCircles
              dataY0={dataY0 as Data}
              dataY1={dataY1 as Data}
              dataYOrigin={dataYOrigin as Data}
              factors={{
                currentFactorMultiplication,
                simulatedFactorMultiplication
              }}
              getCountDisplayedCircles={getCountDisplayedCircles}
              timeSeries={timeSeries}
              xScale={xScale}
            />
          )}

          {dataExclusionPeriods?.map((item, index) => (
            <ThresholdWithPatternLines
              data={item}
              display={
                !isNil(dataExclusionPeriods) && !isEmpty(dataExclusionPeriods)
              }
              graphHeight={height}
              key={item.times[index]}
              leftScale={leftScale}
              rightScale={rightScale}
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
                leftScale,
                rightScale,
                secondUnit,
                unit
              });

              return (
                <g key={metric}>
                  {displayAnchor && (
                    <RegularAnchorPoint
                      areaColor={areaColor}
                      graphHeight={height}
                      graphWidth={width}
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
