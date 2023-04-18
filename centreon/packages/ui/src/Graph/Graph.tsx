import { useMemo, useRef, useState } from 'react';

import { Group } from '@visx/visx';
import { difference } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import {
  getLeftScale,
  getRightScale,
  getSortedStackedLines,
  getXScale
} from '../timeSeries';
import { Line } from '../timeSeries/models';

import Axes from './Axes';
import Grids from './Grids';
import Header from './Header';
import Lines from './Lines';
import RegularAnchorPoint from './Lines/AnchorPoint/RegularAnchorPoint';
import StackedAnchorPoint, {
  StackValue
} from './Lines/AnchorPoint/StackedAnchorPoint';
import useAnchorPoint from './Lines/AnchorPoint/useAnchorPoint';
import useStackedLines from './Lines/StackedLines/useStackedLines';
import { margin } from './common';
import { adjustGraphData } from './helpers';
import {
  AreaAnchorPoint,
  Axis,
  Data,
  GraphData,
  GridsModel,
  ShapeLines
} from './models';

const useStyles = makeStyles()((theme) => ({
  overlay: {
    cursor: 'crosshair'
  }
}));

interface Props {
  anchorPoint?: AreaAnchorPoint;
  axis?: Axis;
  graphData: GraphData | Data;
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

  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const graphWidth = width > 0 ? width - margin.left - margin.right : 0;
  const graphHeight = height > 0 ? height - margin.top - margin.bottom : 0;

  const title =
    'title' in graphData ? graphData.title : graphData?.global?.title;

  const timeSeries =
    'timeSeries' in graphData
      ? graphData.timeSeries
      : adjustGraphData(graphData).timeSeries;
  const lines =
    'lines' in graphData ? graphData.lines : adjustGraphData(graphData).lines;
  const baseAxis =
    'baseAxis' in graphData ? graphData.baseAxis : graphData.global?.base;

  const { regularStackedLines, invertedStackedLines } = useStackedLines({
    lines,
    timeSeries
  });

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

  const mouseLeave = (): void => {
    setEventMouseMoving(null);
  };

  const commonAnchorPoint = {
    displayTimeValues: true,
    graphHeight,
    graphWidth,
    position,
    positionX,
    positionY,
    timeTick
  };

  return (
    <>
      <Header timeTick={timeTick} title={title} />
      <svg height={height} ref={graphSvgRef} width="100%">
        <Group.Group
          className={classes.overlay}
          left={margin.left}
          top={margin.top}
          onMouseLeave={mouseLeave}
          onMouseMove={setEventMouseMoving}
        >
          <Grids
            height={graphHeight}
            leftScale={leftScale}
            width={graphWidth}
            xScale={xScale}
            {...grids}
          />
          <Axes
            data={{
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
        </Group.Group>
      </svg>
    </>
  );
};

export default Graph;
