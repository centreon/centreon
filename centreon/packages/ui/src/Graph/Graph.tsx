import { useMemo, useRef } from 'react';

import { Group } from '@visx/visx';

import { ClickAwayListener } from '@mui/material';

import Axes from './BasicComponents/Axes';
import Grids from './BasicComponents/Grids';
import Lines from './BasicComponents/Lines';
import useRegularLines from './BasicComponents/Lines/RegularLines/useRegularLines';
import useStackedLines from './BasicComponents/Lines/StackedLines/useStackedLines';
import LoadingProgress from './BasicComponents/LoadingProgress';
import useFilterLines from './BasicComponents/useFilterLines';
import { margin } from './common';
import { useStyles } from './Graph.styles';
import Header from './Header';
import InteractionWithGraph from './IntercatifsComponents';
import {
  RegularLinesAnchorPoint,
  StackedAnchorPoint as StackedAnchorPointModel,
  StackValue
} from './IntercatifsComponents/AnchorPoint/models';
import RegularAnchorPoint from './IntercatifsComponents/AnchorPoint/RegularAnchorPoint';
import StackedAnchorPoint from './IntercatifsComponents/AnchorPoint/StackedAnchorPoint';
import useAnchorPoint from './IntercatifsComponents/AnchorPoint/useAnchorPoint';
import GraphTooltip from './IntercatifsComponents/Tooltip';
import useGraphTooltip from './IntercatifsComponents/Tooltip/useGraphTooltip';
import Legend from './Legend';
import { Data, GlobalAreaLines, GraphInterval, GraphProps } from './models';
import { getLeftScale, getRightScale, getXScale } from './timeSeries';

interface Props extends GraphProps {
  graphData: Data;
  graphInterval: GraphInterval;
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
  graphInterval,
  timeShiftZones,
  annotationEvent,
  tooltip
}: Props): JSX.Element => {
  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const { classes } = useStyles();

  const graphWidth = width > 0 ? width - margin.left - margin.right : 0;
  const graphHeight = height > 0 ? height - margin.top - margin.bottom : 0;

  const { title, timeSeries, baseAxis, lines } = graphData;

  const { displayedLines, newLines } = useFilterLines({
    displayThreshold: shapeLines?.areaThresholdLines?.display,
    lines
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
        dataLines: displayedLines,
        dataTimeSeries: timeSeries,
        valueGraphHeight: graphHeight
      }),
    [displayedLines, timeSeries, graphHeight]
  );

  const rightScale = useMemo(
    () =>
      getRightScale({
        dataLines: displayedLines,
        dataTimeSeries: timeSeries,
        valueGraphHeight: graphHeight
      }),
    [timeSeries, displayedLines, graphHeight]
  );

  const { timeTick, positionX, positionY } = useAnchorPoint({
    graphSvgRef,
    timeSeries,
    xScale
  });

  const { stackedLinesData, invertedStackedLinesData } = useStackedLines({
    lines: displayedLines,
    timeSeries
  });

  const { regularLines } = useRegularLines({ lines: displayedLines });

  const graphTooltipData = useGraphTooltip({
    graphWidth,
    timeSeries,
    xScale
  });

  const commonLinesProps = {
    display: true,
    leftScale,
    rightScale,
    xScale
  };

  const areaThreshold = {
    lines: displayedLines,
    timeSeries,
    ...commonLinesProps
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
      <ClickAwayListener onClickAway={graphTooltipData?.hideTooltip}>
        <div className={classes.container}>
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
                  lines: displayedLines,
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
                shape={{ areaRegularLines, areaStackedLines, areaThreshold }}
              />

              <InteractionWithGraph
                annotationData={{ ...annotationEvent }}
                commonData={{ graphHeight, graphSvgRef, graphWidth, xScale }}
                timeShiftZonesData={{
                  ...timeShiftZones,
                  graphInterval,
                  loading
                }}
                zoomData={{ ...zoomPreview }}
              />
            </Group.Group>
          </svg>
          <GraphTooltip {...tooltip} {...graphTooltipData} />
          <Legend base={baseAxis} lines={newLines} timeSeries={timeSeries} />
        </div>
      </ClickAwayListener>
    </>
  );
};

export default Graph;
