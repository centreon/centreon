import { useMemo, useRef } from 'react';

import { Group } from '@visx/visx';
import { useAtomValue } from 'jotai/utils';

import Axes from './Axes';
import Grids from './Grids';
import Header from './Header';
import InteractionWithGraph from './InteractionWithGraph';
import RegularAnchorPoint from './InteractionWithGraph/AnchorPoint/RegularAnchorPoint';
import StackedAnchorPoint from './InteractionWithGraph/AnchorPoint/StackedAnchorPoint';
import {
  RegularLinesAnchorPoint,
  StackValue,
  StackedAnchorPoint as StackedAnchorPointModel
} from './InteractionWithGraph/AnchorPoint/models';
import useAnchorPoint from './InteractionWithGraph/AnchorPoint/useAnchorPoint';
import { eventMouseMovingAtom } from './InteractionWithGraph/interactionWithGraphAtoms';
import Lines from './Lines';
import useRegularLines from './Lines/RegularLines/useRegularLines';
import useStackedLines from './Lines/StackedLines/useStackedLines';
import { margin } from './common';
import { Data, GlobalAreaLines, GraphProps } from './models';
import { getLeftScale, getRightScale, getXScale } from './timeSeries';

interface Props extends GraphProps {
  graphData: Data;
  shapeLines: GlobalAreaLines;
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
  const eventMouseMoving = useAtomValue(eventMouseMovingAtom);

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
    event: eventMouseMoving ?? undefined,
    graphSvgRef,
    timeSeries,
    xScale
  });

  const { stackedLinesData, invertedStackedLinesData } = useStackedLines({
    lines,
    timeSeries
  });

  const { regularLines } = useRegularLines({ lines });

  const commonLinesProps = {
    display: true,
    leftScale,
    rightScale,
    xScale
  };

  const areaRegularLines = {
    lines: regularLines,
    timeSeries,
    ...commonLinesProps,
    ...shapeLines?.areaRegularLines
  };

  const areaStackedLines = {
    invertedStackedLinesData,
    stackedLinesData,
    ...commonLinesProps,
    ...shapeLines?.areaStackedLines
  };

  const displayRegularLinesAnchorPoint =
    anchorPoint?.areaRegularLinesAnchorPoint?.display ?? true;

  const displayStackedAnchorPoint =
    anchorPoint?.areaStackedLinesAnchorPoint?.display ?? true;

  const commonAnchorPoint = {
    displayTimeValues: true,
    graphHeight,
    graphWidth,
    position,
    positionX,
    positionY,
    timeTick
  };

  const renderRegularLinesAnchorPoint = ({
    areaColor,
    lineColor,
    metric,
    timeSeries: regularLinesTimeSeries,
    transparency,
    xScale: xScaleRegularLines,
    yScale
  }: RegularLinesAnchorPoint): JSX.Element => (
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
  );

  const renderStackedAnchorPoint = ({
    areaColor,
    transparency,
    lineColor,
    stack,
    xScale: x,
    yScale: y
  }: StackedAnchorPointModel): JSX.Element => (
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
  );

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
              baseAxis,
              lines,
              timeSeries,
              ...axis
            }}
            graphEndpoint={endpoint}
            height={graphHeight}
            leftScale={leftScale}
            rightScale={rightScale}
            width={graphWidth}
            xScale={xScale}
          />

          <Lines
            anchorPoint={{
              renderRegularLinesAnchorPoint,
              renderStackedAnchorPoint
            }}
            height={graphHeight}
            shape={{ areaRegularLines, areaStackedLines }}
          />

          <InteractionWithGraph
            commonData={{ graphHeight, graphWidth }}
            zoomData={{
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
