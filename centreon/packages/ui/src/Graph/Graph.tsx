import { useMemo, useRef } from 'react';

import { Group } from '@visx/visx';

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
import Lines from './Lines';
import useRegularLines from './Lines/RegularLines/useRegularLines';
import useStackedLines from './Lines/StackedLines/useStackedLines';
import LoadingProgress from './LoadingProgress';
import { margin } from './common';
import { Data, GlobalAreaLines, GraphParameters, GraphProps } from './models';
import { getLeftScale, getRightScale, getXScale } from './timeSeries';

interface Props extends GraphProps {
  graphData: Data;
  graphInterval: GraphParameters;
  loading: boolean;
  shapeLines?: GlobalAreaLines;
}

const Graph = ({
  graphData,
  height,
  width,
  shapeLines,
  axis,
  anchorPoint,
  loading,
  zoomPreview,
  graphInterval
}: Props): JSX.Element => {
  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const graphWidth = width > 0 ? width - margin.left - margin.right : 0;
  const graphHeight = height > 0 ? height - margin.top - margin.bottom : 0;

  const { title, timeSeries, lines, baseAxis } = graphData;

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

  const { timeTick, positionX, positionY } = useAnchorPoint({
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

  const displayTimeTick =
    displayRegularLinesAnchorPoint ?? displayStackedAnchorPoint;

  const commonAnchorPoint = {
    displayTimeValues: true,
    graphHeight,
    graphWidth,
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
        />
      )}
    </g>
  );

  return (
    <>
      <Header
        displayTimeTick={displayTimeTick}
        timeTick={timeTick}
        title={title}
      />
      <LoadingProgress display={loading} height={height} width={width} />
      <svg height={height} ref={graphSvgRef} width="100%">
        <Group.Group left={margin.left} top={margin.top}>
          <Grids
            height={graphHeight}
            leftScale={leftScale}
            width={graphWidth}
            xScale={xScale}
          />

          <Axes
            data={{
              baseAxis,
              lines,
              timeSeries,
              ...axis
            }}
            graphInterval={graphInterval}
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
            commonData={{ graphHeight, graphSvgRef, graphWidth }}
            zoomData={{
              xScale,
              ...zoomPreview
            }}
          />
        </Group.Group>
      </svg>
    </>
  );
};

export default Graph;
