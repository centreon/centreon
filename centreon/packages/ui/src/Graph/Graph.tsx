import { useMemo, useRef, useState } from 'react';

import { Group } from '@visx/visx';
import dayjs from 'dayjs';
import { difference, gte } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import Axes from './Axes';
import Grids from './Grids';
import Header from './Header';
import InteractionWithGraph from './InteractionWithGraph';
import RegularAnchorPoint from './InteractionWithGraph/AnchorPoint/RegularAnchorPoint';
import StackedAnchorPoint, {
  StackValue
} from './InteractionWithGraph/AnchorPoint/StackedAnchorPoint';
import useAnchorPoint from './InteractionWithGraph/AnchorPoint/useAnchorPoint';
import Bar from './InteractionWithGraph/Bar';
import Lines from './Lines';
import useStackedLines from './Lines/StackedLines/useStackedLines';
import { dateFormat, margin, timeFormat } from './common';
import { AreaAnchorPoint, Axis, Data, GridsModel, ShapeLines } from './models';
import {
  getLeftScale,
  getRightScale,
  getSortedStackedLines,
  getXScale
} from './timeSeries';
import { Line } from './timeSeries/models';

const useStyles = makeStyles()(() => ({
  overlay: {
    cursor: 'crosshair'
  }
}));

interface Props {
  anchorPoint?: AreaAnchorPoint;
  axis?: Axis;
  graphData: Data;
  grids?: GridsModel;
  height: number;
  shapeLines?: ShapeLines;
  width: number;
}

const Graph = ({
  graphData,
  height,
  width,
  shapeLines,
  axis,
  grids,
  anchorPoint
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const [eventMouseMoving, setEventMouseMoving] = useState<null | any>(null);
  const [eventMouseDown, setEventMouseDown] = useState<null | any>(null);

  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const graphWidth = width > 0 ? width - margin.left - margin.right : 0;
  const graphHeight = height > 0 ? height - margin.top - margin.bottom : 0;

  const { title, timeSeries, lines, baseAxis, endpoint } = graphData;

  const xScale = useMemo(
    () =>
      getXScale({
        dataTime: timeSeries,
        valueWidth: graphWidth
      }),
    [timeSeries, graphWidth]
  );

  const leftScale = useMemo(
    () =>
      getLeftScale({
        dataLines: lines,
        dataTimeSeries: timeSeries,
        valueGraphHeight: graphHeight
      }),
    [lines, timeSeries, graphHeight]
  );

  const rightScale = useMemo(
    () =>
      getRightScale({
        dataLines: lines,
        dataTimeSeries: timeSeries,
        valueGraphHeight: graphHeight
      }),
    [timeSeries, lines, graphHeight]
  );

  const { position, timeTick, positionX, positionY } = useAnchorPoint({
    event: eventMouseMoving,
    graphSvgRef,
    timeSeries,
    xScale
  });

  const { regularStackedLines, invertedStackedLines } = useStackedLines({
    lines,
    timeSeries
  });

  const regularLines = (): Array<Line> => {
    const stackedLines = getSortedStackedLines(lines);

    return difference(lines, stackedLines);
  };

  const commonLinesProps = {
    display: true,
    leftScale,
    rightScale,
    xScale
  };

  const areaRegularLines = {
    lines: regularLines(),
    timeSeries,
    ...commonLinesProps,
    ...shapeLines?.areaRegularLinesData
  };

  const regularStackedLinesData = {
    lines: regularStackedLines.lines,
    timeSeries: regularStackedLines.timeSeries
  };

  const invertedStackedLinesData = {
    lines: invertedStackedLines.lines,
    timeSeries: invertedStackedLines.timeSeries
  };
  const displayStackedAnchorPoint =
    anchorPoint?.areaStackedLinesAnchorPoint?.display ?? true;
  const displayRegularLinesAnchorPoint =
    anchorPoint?.areaRegularLinesAnchorPoint?.display ?? true;

  const commonAnchorPoint = {
    displayTimeValues: true,
    graphHeight,
    graphWidth,
    position,
    positionX,
    positionY,
    timeTick
  };
  const mouseLeave = (): void => {
    setEventMouseMoving(null);
    setEventMouseDown(null);
  };

  const mouseUp = (): void => {
    setEventMouseDown(null);
  };

  const getXAxisTickFormat = (): string => {
    const { queryParameters } = endpoint;
    const numberDays = dayjs
      .duration(dayjs(queryParameters.end).diff(dayjs(queryParameters.start)))
      .asDays();

    return gte(numberDays, 2) ? dateFormat : timeFormat;
  };

  return (
    <>
      <Header timeTick={timeTick} title={title} />
      <svg height={height} ref={graphSvgRef} width="100%">
        <Group.Group left={margin.left} top={margin.top}>
          <Grids
            height={graphHeight}
            leftScale={leftScale}
            width={graphWidth}
            xScale={xScale}
            {...grids}
          />

          <Axes
            data={{
              axisX: { xAxisTickFormat: getXAxisTickFormat() },
              baseAxis,
              lines,
              timeSeries,
              ...axis
            }}
            height={graphHeight}
            leftScale={leftScale}
            rightScale={rightScale}
            width={graphWidth}
            xScale={xScale}
          />

          <Lines
            anchorPoint={{
              renderRegularLinesAnchorPoint: ({
                areaColor,
                lineColor,
                metric,
                regularLinesTimeSeries,
                transparency,
                xScaleRegularLines,
                yScale
              }: any): JSX.Element => (
                <g>
                  {displayRegularLinesAnchorPoint && (
                    <RegularAnchorPoint
                      areaColor={areaColor}
                      lineColor={lineColor}
                      metric={metric}
                      timeSeries={regularLinesTimeSeries}
                      transparency={transparency}
                      xScale={xScaleRegularLines}
                      yScale={yScale}
                      {...commonAnchorPoint}
                      {...anchorPoint?.areaRegularLinesAnchorPoint}
                    />
                  )}
                </g>
              ),
              renderStackedAnchorPoint: ({
                areaColor,
                transparency,
                lineColor,
                stack,
                xScale: x,
                yScale: y
              }: any): JSX.Element => (
                <g>
                  {displayStackedAnchorPoint && (
                    <StackedAnchorPoint
                      areaColor={areaColor}
                      lineColor={lineColor}
                      stackValues={stack as unknown as Array<StackValue>}
                      transparency={transparency}
                      xScale={x}
                      yScale={y}
                      {...commonAnchorPoint}
                      {...anchorPoint?.areaStackedLinesAnchorPoint}
                    />
                  )}
                </g>
              )
            }}
            height={graphHeight}
            shape={{
              areaRegularLines,
              areaStackedLines: {
                ...commonLinesProps,
                invertedStackedLinesData,
                regularStackedLinesData,
                ...shapeLines?.areaStackedLinesData
              }
            }}
          />

          <InteractionWithGraph
            renderAreaToInteractWith={
              <Bar
                className={classes.overlay}
                fill="transparent"
                height={graphHeight}
                width={graphWidth}
                x={0}
                y={0}
                onMouseDown={setEventMouseDown}
                onMouseLeave={mouseLeave}
                onMouseMove={setEventMouseMoving}
                onMouseUp={mouseUp}
              />
            }
            zoomPreviewData={{
              eventMouseDown,
              graphHeight,
              graphWidth,
              positionX,
              xScale
            }}
          />
        </Group.Group>
      </svg>
    </>
  );
};

export default Graph;
