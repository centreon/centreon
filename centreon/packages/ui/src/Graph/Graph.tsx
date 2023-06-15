import { MutableRefObject, useMemo, useRef } from 'react';

import { Group } from '@visx/visx';
import { isNil } from 'ramda';

import { ClickAwayListener, Skeleton } from '@mui/material';

import Axes from './BasicComponents/Axes';
import Grids from './BasicComponents/Grids';
import Lines from './BasicComponents/Lines';
import LoadingProgress from './BasicComponents/LoadingProgress';
import useFilterLines from './BasicComponents/useFilterLines';
import { useStyles } from './Graph.styles';
import Header from './Header';
import InteractionWithGraph from './InteractiveComponents';
import GraphTooltip from './InteractiveComponents/Tooltip';
import useGraphTooltip from './InteractiveComponents/Tooltip/useGraphTooltip';
import Legend from './Legend';
import { margin } from './common';
import {
  Data,
  GlobalAreaLines,
  GraphInterval,
  GraphProps,
  LegendModel
} from './models';
import { getLeftScale, getRightScale, getXScale } from './timeSeries';
import { useIntersection } from './useGraphIntersection';
import { canDisplayThreshold } from './BasicComponents/Lines/Threshold/models';

interface Props extends GraphProps {
  graphData: Data;
  graphInterval: GraphInterval;
  graphRef: MutableRefObject<HTMLDivElement | null>;
  legend?: LegendModel;
  shapeLines?: GlobalAreaLines;
}

const Graph = ({
  graphData,
  height = 500,
  width,
  shapeLines,
  axis,
  displayAnchor = true,
  loading,
  zoomPreview,
  graphInterval,
  timeShiftZones,
  annotationEvent,
  tooltip,
  legend,
  graphRef,
  header
}: Props): JSX.Element => {
  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const { classes } = useStyles();
  const { isInViewport } = useIntersection({ element: graphRef?.current });

  const graphWidth = width > 0 ? width - margin.left - margin.right : 0;
  const graphHeight = height > 0 ? height - margin.top - margin.bottom : 0;

  const { title, timeSeries, baseAxis, lines } = graphData;

  const { displayedLines, newLines } = useFilterLines({
    displayThreshold: canDisplayThreshold(shapeLines?.areaThresholdLines),
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

  const graphTooltipData = useGraphTooltip({
    graphWidth,
    timeSeries,
    xScale
  });

  const displayLegend = legend?.display ?? true;
  const displayTooltip = !isNil(tooltip?.renderComponent);

  if (!isInViewport) {
    return (
      <Skeleton
        height={graphSvgRef?.current?.clientHeight ?? graphHeight}
        variant="rectangular"
        width="100%"
      />
    );
  }

  return (
    <>
      <Header
        displayTimeTick={displayAnchor}
        header={header}
        timeSeries={timeSeries}
        title={title}
        xScale={xScale}
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
                displayAnchor={displayAnchor}
                displayedLines={displayedLines}
                graphSvgRef={graphSvgRef}
                height={graphHeight}
                leftScale={leftScale}
                rightScale={rightScale}
                timeSeries={timeSeries}
                width={graphWidth}
                xScale={xScale}
                {...shapeLines}
              />

              <InteractionWithGraph
                annotationData={{ ...annotationEvent }}
                commonData={{
                  graphHeight,
                  graphSvgRef,
                  graphWidth,
                  timeSeries,
                  xScale
                }}
                timeShiftZonesData={{
                  ...timeShiftZones,
                  graphInterval,
                  loading
                }}
                zoomData={{ ...zoomPreview }}
              />
            </Group.Group>
          </svg>
          {displayTooltip && (
            <GraphTooltip {...tooltip} {...graphTooltipData} />
          )}
          {displayLegend && (
            <Legend
              base={baseAxis}
              displayAnchor={displayAnchor}
              lines={newLines}
              renderExtraComponent={legend?.renderExtraComponent}
              timeSeries={timeSeries}
            />
          )}
        </div>
      </ClickAwayListener>
    </>
  );
};

export default Graph;
