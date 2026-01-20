import type { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';
import type { MutableRefObject } from 'react';

import {
  getDates,
  getTimeSeriesForLines,
  getYScale
} from '../../../common/timeSeries';
import type { Line, TimeValue } from '../../../common/timeSeries/models';
import { getPointRadius, getStyle } from '../../../common/utils';
import { displayArea } from '../../helpers/index';
import GuidingLines from '../../InteractiveComponents/AnchorPoint/GuidingLines';
import RegularAnchorPoint, {
  getYAnchorPoint
} from '../../InteractiveComponents/AnchorPoint/RegularAnchorPoint';
import type { DisplayAnchor, GlobalAreaLines, LineStyle } from '../../models';
import Point from './Point';
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
  scale?: 'linear' | 'logarithmic';
  scaleLogarithmicBase?: number;
  timeSeries: Array<TimeValue>;
  width: number;
  xScale: ScaleLinear<number, number>;
  yScalesPerUnit: Record<string, ScaleLinear<number, number>>;
  lineStyle: LineStyle | Array<LineStyle>;
  hasSecondUnit?: boolean;
  maxLeftAxisCharacters: number;
}

const Lines = ({
  height,
  graphSvgRef,
  width,
  displayAnchor,
  yScalesPerUnit,
  xScale,
  timeSeries,
  displayedLines,
  areaThresholdLines,
  areaStackedLines,
  areaRegularLines,
  scale,
  scaleLogarithmicBase,
  lineStyle,
  hasSecondUnit,
  maxLeftAxisCharacters
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
    displayAnchor: displayGuidingLines,
    graphHeight: height,
    graphSvgRef,
    graphWidth: width,
    hasSecondUnit,
    maxLeftAxisCharacters,
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
          {Object.entries(stackedLinesData).map(
            ([stackedKey, { lines, timeSeries: stackedTimeSeries }]) => {
              const [, unit] = stackedKey.split('-');
              const yScale =
                unit === '' && yScalesPerUnit[unit] === undefined
                  ? yScalesPerUnit[undefined]
                  : yScalesPerUnit[unit];

              return (
                <StackedLines
                  key={`stacked-${unit}`}
                  lineStyle={lineStyle}
                  lines={lines}
                  timeSeries={stackedTimeSeries}
                  yScale={yScale}
                  {...commonStackedLinesProps}
                />
              );
            }
          )}
          {Object.entries(invertedStackedLinesData).map(
            ([stackedKey, { lines, timeSeries: stackedTimeSeries }]) => {
              const [, unit] = stackedKey.split('-');
              return (
                <StackedLines
                  key={`invert-stacked-${unit}`}
                  lineStyle={lineStyle}
                  lines={lines}
                  timeSeries={stackedTimeSeries}
                  yScale={getYScale({
                    invert: '1',
                    scale,
                    scaleLogarithmicBase,
                    unit:
                      unit === '' && yScalesPerUnit[unit] === undefined
                        ? undefined
                        : unit,
                    yScalesPerUnit
                  })}
                  {...commonStackedLinesProps}
                />
              );
            }
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
              metric_id,
              ...rest
            }) => {
              const yScale = getYScale({
                invert,
                scale,
                scaleLogarithmicBase,
                unit,
                yScalesPerUnit
              });
              const relatedTimeSeries = getTimeSeriesForLines({
                invert,
                lines: [
                  {
                    areaColor,
                    filled,
                    highlight,
                    invert,
                    lineColor,
                    metric_id,
                    transparency,
                    unit,
                    ...rest
                  }
                ],
                timeSeries
              });

              const style = getStyle({
                metricId: metric_id,
                style: lineStyle
              }) as LineStyle;

              return (
                <g key={metric_id}>
                  {displayGuidingLines && (
                    <RegularAnchorPoint
                      areaColor={areaColor || lineColor}
                      lineColor={lineColor}
                      maxLeftAxisCharacters={maxLeftAxisCharacters}
                      metric_id={metric_id}
                      timeSeries={relatedTimeSeries}
                      transparency={transparency}
                      xScale={xScale}
                      yScale={yScale}
                      maxLeftAxisCharacters={maxLeftAxisCharacters}
                      hasSecondUnit={hasSecondUnit}
                    />
                  )}
                  {style?.showPoints &&
                    getDates(relatedTimeSeries).map((timeTick) => (
                      <Point
                        key={timeTick.toString()}
                        lineColor={lineColor}
                        metric_id={metric_id}
                        radius={getPointRadius(style?.lineWidth)}
                        timeSeries={relatedTimeSeries}
                        timeTick={timeTick}
                        xScale={xScale}
                        yPoint={getYAnchorPoint({
                          metric_id,
                          timeSeries: relatedTimeSeries,
                          timeTick,
                          yScale
                        })}
                        yScale={yScale}
                      />
                    ))}
                  <RegularLine
                    areaColor={areaColor || lineColor}
                    curve={style?.curve || 'linear'}
                    dashLength={style?.dashLength}
                    dashOffset={style?.dashOffset}
                    dotOffset={style?.dotOffset}
                    filled={isNil(style?.showArea) ? filled : style.showArea}
                    graphHeight={height}
                    highlight={highlight}
                    lineColor={lineColor}
                    lineWidth={style?.lineWidth || 2}
                    metric_id={metric_id}
                    timeSeries={relatedTimeSeries}
                    transparency={
                      isNil(style?.areaTransparency)
                        ? transparency || 80
                        : style.areaTransparency
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
