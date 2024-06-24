import { MutableRefObject, useMemo, useRef, useState } from 'react';

import { Group, Tooltip } from '@visx/visx';
import { flatten, gt, isNil, lte, pluck, reduce } from 'ramda';

import { ClickAwayListener, Fade, Skeleton, useTheme } from '@mui/material';

import { getLeftScale, getRightScale, getXScale } from '../common/timeSeries';
import { Line } from '../common/timeSeries/models';
import { Thresholds as ThresholdsModel } from '../common/models';
import { Tooltip as MuiTooltip } from '../../components/Tooltip';

import Axes from './BasicComponents/Axes';
import Grids from './BasicComponents/Grids';
import Lines from './BasicComponents/Lines';
import { canDisplayThreshold } from './BasicComponents/Lines/Threshold/models';
import useFilterLines from './BasicComponents/useFilterLines';
import { useStyles } from './LineChart.styles';
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
  LineChartProps,
  LegendModel
} from './models';
import { useIntersection } from './useLineChartIntersection';
import { CurveType } from './BasicComponents/Lines/models';
import Thresholds from './BasicComponents/Thresholds';
import { legendWidth } from './Legend/Legend.styles';
import GraphValueTooltip from './InteractiveComponents/GraphValueTooltip/GraphValueTooltip';

const extraMargin = 10;

interface Props extends LineChartProps {
  curve: CurveType;
  graphData: Data;
  graphInterval: GraphInterval;
  graphRef: MutableRefObject<HTMLDivElement | null>;
  legend?: LegendModel;
  limitLegend?: false | number;
  shapeLines?: GlobalAreaLines;
  thresholdUnit?: string;
  thresholds?: ThresholdsModel;
}

const baseStyles = {
  ...Tooltip.defaultStyles,
  textAlign: 'center'
};

const LineChart = ({
  graphData,
  height = 500,
  width,
  shapeLines,
  axis,
  displayAnchor,
  zoomPreview,
  graphInterval,
  timeShiftZones,
  annotationEvent,
  tooltip,
  legend,
  graphRef,
  header,
  curve,
  thresholds,
  thresholdUnit,
  limitLegend
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const theme = useTheme();

  const [linesGraph, setLinesGraph] = useState<Array<Line> | null>(null);
  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const { isInViewport } = useIntersection({ element: graphRef?.current });

  const legendRef = useRef<HTMLDivElement | null>(null);

  const {
    tooltipOpen: thresholdTooltipOpen,
    tooltipLeft: thresholdTooltipLeft,
    tooltipTop: thresholdTooltipTop,
    tooltipData: thresholdTooltipData,
    hideTooltip: hideThresholdTooltip,
    showTooltip: showThresholdTooltip
  } = Tooltip.useTooltip();

  const graphWidth =
    width > 0 ? width - margin.left - margin.right - extraMargin : 0;
  const graphHeight =
    (height || 0) > 0
      ? (height || 0) -
        margin.top -
        5 -
        (legendRef.current?.getBoundingClientRect().height || 0)
      : 0;

  const { title, timeSeries, baseAxis, lines } = graphData;

  const thresholdValues = flatten([
    pluck('value', thresholds?.warning || []),
    pluck('value', thresholds?.critical || [])
  ]);

  const { displayedLines, newLines } = useFilterLines({
    displayThreshold: canDisplayThreshold(shapeLines?.areaThresholdLines),
    lines,
    linesGraph,
    setLinesGraph
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
        thresholdUnit,
        thresholds: (thresholds?.enabled && thresholdValues) || [],
        valueGraphHeight: graphHeight - 35
      }),
    [displayedLines, timeSeries, graphHeight, thresholdValues]
  );

  const rightScale = useMemo(
    () =>
      getRightScale({
        dataLines: displayedLines,
        dataTimeSeries: timeSeries,
        thresholdUnit,
        thresholds: (thresholds?.enabled && thresholdValues) || [],
        valueGraphHeight: graphHeight - 35
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

  const legendItemsWidth = reduce(
    (acc) => acc + legendWidth * 8 + 24,
    0,
    displayedLines
  );

  const shouldDisplayLegendInCompactMode =
    lte(graphWidth, 808) && gt(legendItemsWidth, graphWidth);

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
      <Header header={header} title={title} />
      <ClickAwayListener onClickAway={graphTooltipData?.hideTooltip}>
        <MuiTooltip
          classes={{
            tooltip: classes.graphValueTooltip
          }}
          placement="top-start"
          title={<GraphValueTooltip base={baseAxis} />}
        >
          <div className={classes.container}>
            <svg
              height={graphHeight + margin.top}
              ref={graphSvgRef}
              width="100%"
            >
              <Group.Group
                left={margin.left + extraMargin / 2}
                top={margin.top}
              >
                <Grids
                  height={graphHeight - margin.top}
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
                  height={graphHeight - margin.top}
                  leftScale={leftScale}
                  rightScale={rightScale}
                  width={graphWidth}
                  xScale={xScale}
                />

                <Lines
                  curve={curve}
                  displayAnchor={displayAnchor}
                  displayedLines={displayedLines}
                  graphSvgRef={graphSvgRef}
                  height={graphHeight - margin.top}
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
                    leftScale,
                    lines: displayedLines,
                    rightScale,
                    timeSeries,
                    xScale
                  }}
                  timeShiftZonesData={{
                    ...timeShiftZones,
                    graphInterval
                  }}
                  zoomData={{ ...zoomPreview }}
                />

                {thresholds?.enabled && (
                  <Thresholds
                    displayedLines={displayedLines}
                    hideTooltip={hideThresholdTooltip}
                    leftScale={leftScale}
                    rightScale={rightScale}
                    showTooltip={showThresholdTooltip}
                    thresholdUnit={thresholdUnit}
                    thresholds={thresholds as ThresholdsModel}
                    width={graphWidth}
                  />
                )}
              </Group.Group>
            </svg>
            {displayTooltip && (
              <GraphTooltip {...tooltip} {...graphTooltipData} />
            )}
            <Fade in={thresholdTooltipOpen}>
              <Tooltip.Tooltip
                left={thresholdTooltipLeft}
                style={{
                  ...baseStyles,
                  backgroundColor: theme.palette.background.paper,
                  color: theme.palette.text.primary,
                  transform: `translate(${graphWidth / 2}px, -10px)`
                }}
                top={thresholdTooltipTop}
              >
                {thresholdTooltipData}
              </Tooltip.Tooltip>
            </Fade>
          </div>
        </MuiTooltip>
      </ClickAwayListener>
      {displayLegend && (
        <div ref={legendRef}>
          <Legend
            base={baseAxis}
            limitLegend={limitLegend}
            lines={newLines}
            renderExtraComponent={legend?.renderExtraComponent}
            setLinesGraph={setLinesGraph}
            shouldDisplayLegendInCompactMode={shouldDisplayLegendInCompactMode}
          />
        </div>
      )}
    </>
  );
};

export default LineChart;
