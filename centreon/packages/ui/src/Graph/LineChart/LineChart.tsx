import { MutableRefObject, useMemo, useRef, useState } from 'react';

import { Group, Tooltip } from '@visx/visx';
import { equals, flatten, gt, isNil, lte, pluck, reduce } from 'ramda';

import {
  ClickAwayListener,
  Fade,
  Skeleton,
  Stack,
  useTheme
} from '@mui/material';

import {
  getLeftScale,
  getRightScale,
  getUnits,
  getXScale
} from '../common/timeSeries';
import { Line } from '../common/timeSeries/models';
import { Thresholds as ThresholdsModel } from '../common/models';
import { Tooltip as MuiTooltip } from '../../components/Tooltip';
import { useTooltipStyles } from '../common/useTooltipStyles';

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
import { Data, GlobalAreaLines, GraphInterval, LineChartProps } from './models';
import { useIntersection } from './useLineChartIntersection';
import Thresholds from './BasicComponents/Thresholds';
import { legendWidth } from './Legend/Legend.styles';
import GraphValueTooltip from './InteractiveComponents/GraphValueTooltip/GraphValueTooltip';

const extraMargin = 10;

interface Props extends LineChartProps {
  graphData: Data;
  graphInterval: GraphInterval;
  graphRef: MutableRefObject<HTMLDivElement | null>;
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
  lineStyle,
  thresholds,
  thresholdUnit,
  limitLegend
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { classes: tooltipClasses } = useTooltipStyles();

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

  const legendBoundingHeight =
    !equals(legend?.display, false) &&
    (isNil(legend?.placement) || equals(legend?.placement, 'bottom'))
      ? legendRef.current?.getBoundingClientRect().height || 0
      : 0;
  const legendBoundingWidth =
    !equals(legend?.display, false) &&
    (equals(legend?.placement, 'left') || equals(legend?.placement, 'right'))
      ? legendRef.current?.getBoundingClientRect().width || 0
      : 0;

  const [, secondUnit] = getUnits(displayedLines);

  const graphWidth =
    width > 0
      ? width -
        margin.left -
        (secondUnit ? margin.right : 8) -
        extraMargin -
        legendBoundingWidth
      : 0;
  const graphHeight =
    (height || 0) > 0
      ? (height || 0) - margin.top - 5 - legendBoundingHeight
      : 0;

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
        isCenteredZero: axis?.isCenteredZero,
        scale: axis?.scale,
        scaleLogarithmicBase: axis?.scaleLogarithmicBase,
        thresholdUnit,
        thresholds: (thresholds?.enabled && thresholdValues) || [],
        valueGraphHeight: graphHeight - 35
      }),
    [
      displayedLines,
      timeSeries,
      graphHeight,
      thresholdValues,
      axis?.isCenteredZero,
      axis?.scale,
      axis?.scaleLogarithmicBase
    ]
  );

  const rightScale = useMemo(
    () =>
      getRightScale({
        dataLines: displayedLines,
        dataTimeSeries: timeSeries,
        isCenteredZero: axis?.isCenteredZero,
        scale: axis?.scale,
        scaleLogarithmicBase: axis?.scaleLogarithmicBase,
        thresholdUnit,
        thresholds: (thresholds?.enabled && thresholdValues) || [],
        valueGraphHeight: graphHeight - 35
      }),
    [
      timeSeries,
      displayedLines,
      graphHeight,
      axis?.isCenteredZero,
      axis?.scale,
      axis?.scaleLogarithmicBase
    ]
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

  const displayLegendInBottom =
    isNil(legend?.placement) || equals(legend?.placement, 'bottom');

  const shouldDisplayLegendInCompactMode =
    lte(graphWidth, 808) &&
    gt(legendItemsWidth, graphWidth) &&
    displayLegendInBottom;

  const showGridLines = useMemo(
    () => isNil(axis?.showGridLines) || axis?.showGridLines,
    [axis?.showGridLines]
  );

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
          data-disablePadding
          classes={{
            tooltip: tooltipClasses.tooltip
          }}
          placement="top-start"
          title={
            equals('hidden', tooltip?.mode) ? null : (
              <GraphValueTooltip
                base={baseAxis}
                isSingleMode={equals('single', tooltip?.mode)}
                sortOrder={tooltip?.sortOrder}
              />
            )
          }
        >
          <div className={classes.container}>
            <Stack
              direction={
                equals(legend?.placement, 'left') ? 'row' : 'row-reverse'
              }
            >
              {displayLegend &&
                (equals(legend?.placement, 'left') ||
                  equals(legend?.placement, 'right')) && (
                  <div ref={legendRef} style={{ maxWidth: '60%' }}>
                    <Legend
                      base={baseAxis}
                      height={height}
                      limitLegend={limitLegend}
                      lines={newLines}
                      mode={legend?.mode}
                      placement="left"
                      renderExtraComponent={legend?.renderExtraComponent}
                      setLinesGraph={setLinesGraph}
                      shouldDisplayLegendInCompactMode={
                        shouldDisplayLegendInCompactMode
                      }
                      width={width}
                    />
                  </div>
                )}
              <svg
                height={graphHeight + margin.top}
                ref={graphSvgRef}
                width="100%"
              >
                <Group.Group
                  left={margin.left + extraMargin / 2}
                  top={margin.top}
                >
                  {showGridLines && (
                    <Grids
                      gridLinesType={axis?.gridLinesType}
                      height={graphHeight - margin.top}
                      leftScale={leftScale}
                      width={graphWidth}
                      xScale={xScale}
                    />
                  )}
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
                    areaTransparency={lineStyle?.areaTransparency}
                    curve={lineStyle?.curve || 'linear'}
                    dashLength={lineStyle?.dashLength}
                    dashOffset={lineStyle?.dashOffset}
                    displayAnchor={displayAnchor}
                    displayedLines={displayedLines}
                    dotOffset={lineStyle?.dotOffset}
                    graphSvgRef={graphSvgRef}
                    height={graphHeight - margin.top}
                    leftScale={leftScale}
                    lineWidth={lineStyle?.lineWidth}
                    rightScale={rightScale}
                    showArea={lineStyle?.showArea}
                    showPoints={lineStyle?.showPoints}
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
            </Stack>
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
      {displayLegend && displayLegendInBottom && (
        <div ref={legendRef}>
          <Legend
            base={baseAxis}
            height={height}
            limitLegend={limitLegend}
            lines={newLines}
            mode={legend?.mode}
            placement="bottom"
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
