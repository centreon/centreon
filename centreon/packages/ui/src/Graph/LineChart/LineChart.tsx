import { MutableRefObject, useMemo, useRef, useState } from 'react';

import { Tooltip } from '@visx/visx';
import { equals, flatten, isNil, pluck } from 'ramda';

import { ClickAwayListener, Fade, Skeleton, useTheme } from '@mui/material';

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
import BaseChart from '../common/BaseChart/BaseChart';
import { useComputeBaseChartDimensions } from '../common/BaseChart/useComputeBaseChartDimensions';
import ChartSvgWrapper from '../common/BaseChart/ChartSvgWrapper';

import Lines from './BasicComponents/Lines';
import { canDisplayThreshold } from './BasicComponents/Lines/Threshold/models';
import useFilterLines from './BasicComponents/useFilterLines';
import Header from './Header';
import InteractionWithGraph from './InteractiveComponents';
import GraphTooltip from './InteractiveComponents/Tooltip';
import useGraphTooltip from './InteractiveComponents/Tooltip/useGraphTooltip';
import { margin } from './common';
import { Data, GlobalAreaLines, GraphInterval, LineChartProps } from './models';
import { useIntersection } from './useLineChartIntersection';
import Thresholds from './BasicComponents/Thresholds';
import GraphValueTooltip from './InteractiveComponents/GraphValueTooltip/GraphValueTooltip';
import { useLineChartStyles } from './LineChart.styles';

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
  const { classes } = useLineChartStyles();
  const { classes: tooltipClasses, cx } = useTooltipStyles();

  const theme = useTheme();

  const [linesGraph, setLinesGraph] = useState<Array<Line> | null>(null);
  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const { isInViewport } = useIntersection({ element: graphRef?.current });

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

  const { displayedLines } = useFilterLines({
    displayThreshold: canDisplayThreshold(shapeLines?.areaThresholdLines),
    lines,
    linesGraph
  });

  const [, secondUnit] = getUnits(displayedLines);

  const { legendRef, graphWidth, graphHeight } = useComputeBaseChartDimensions({
    hasSecondUnit: Boolean(secondUnit),
    height,
    legendDisplay: legend?.display,
    legendHeight: legend?.height,
    legendPlacement: legend?.placement,
    width
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
        <>
          <BaseChart
            base={baseAxis}
            graphWidth={graphWidth}
            height={height}
            legend={{
              displayLegend,
              legendHeight: legend?.height,
              mode: legend?.mode,
              placement: legend?.placement,
              renderExtraComponent: legend?.renderExtraComponent
            }}
            legendRef={legendRef}
            limitLegend={limitLegend}
            lines={displayedLines}
            setLines={setLinesGraph}
          >
            <MuiTooltip
              classes={{
                tooltip: cx(
                  tooltipClasses.tooltip,
                  tooltipClasses.tooltipDisablePadding
                )
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
              <div className={classes.tooltipChildren}>
                <ChartSvgWrapper
                  axis={axis}
                  base={baseAxis}
                  displayedLines={displayedLines}
                  graphHeight={graphHeight}
                  graphWidth={graphWidth}
                  gridLinesType={axis?.gridLinesType}
                  leftScale={leftScale}
                  rightScale={rightScale}
                  showGridLines={showGridLines}
                  svgRef={graphSvgRef}
                  timeSeries={timeSeries}
                  xScale={xScale}
                >
                  <>
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
                  </>
                </ChartSvgWrapper>
              </div>
            </MuiTooltip>
          </BaseChart>
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
        </>
      </ClickAwayListener>
    </>
  );
};

export default LineChart;
