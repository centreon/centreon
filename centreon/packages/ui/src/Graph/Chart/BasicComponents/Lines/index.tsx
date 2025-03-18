import type { MutableRefObject } from 'react';

import type { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';

import {
  getDates,
  getTimeSeriesForLines,
  getYScale
} from '../../../common/timeSeries';
import type { Line, TimeValue } from '../../../common/timeSeries/models';
import { getPointRadius, getStyle } from '../../../common/utils';
import GuidingLines from '../../InteractiveComponents/AnchorPoint/GuidingLines';
import RegularAnchorPoint, {
  getYAnchorPoint
} from '../../InteractiveComponents/AnchorPoint/RegularAnchorPoint';
import { displayArea } from '../../helpers/index';
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
  lineStyle
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
            ([unit, { lines, timeSeries: stackedTimeSeries }]) => (
              <StackedLines
                lineStyle={lineStyle}
                key={`stacked-${unit}`}
                lines={lines}
                timeSeries={stackedTimeSeries}
                yScale={yScalesPerUnit[unit]}
                {...commonStackedLinesProps}
              />
            )
          )}
          {Object.entries(invertedStackedLinesData).map(
            ([unit, { lines, timeSeries: stackedTimeSeries }]) => (
              <StackedLines
                lineStyle={lineStyle}
                key={`invert-stacked-${unit}`}
                lines={lines}
                timeSeries={stackedTimeSeries}
                yScale={getYScale({
                  invert: '1',
                  scale,
                  scaleLogarithmicBase,
                  unit,
                  yScalesPerUnit
                })}
                {...commonStackedLinesProps}
              />
            )
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
                style: lineStyle,
                metricId: metric_id
              }) as LineStyle;

              return (
                <g key={metric_id}>
                  {displayGuidingLines && (
                    <RegularAnchorPoint
                      areaColor={areaColor || lineColor}
                      lineColor={lineColor}
                      metric_id={metric_id}
                      timeSeries={relatedTimeSeries}
                      transparency={transparency}
                      xScale={xScale}
                      yScale={yScale}
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
