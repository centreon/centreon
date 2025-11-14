import {
  MutableRefObject,
  ReactElement,
  useEffect,
  useMemo,
  useRef,
  useState
} from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { equals, flatten, gte, has, identity, isNil, pluck } from 'ramda';

import { Skeleton } from '@mui/material';

import { Tooltip } from '../../components';
import { useDeepCompare } from '../../utils';
import InteractionWithGraph from '../Chart/InteractiveComponents';
import { applyingZoomAtomAtom } from '../Chart/InteractiveComponents/ZoomPreview/zoomPreviewAtoms';
import { margin } from '../Chart/common';
import { Data, LineChartProps } from '../Chart/models';
import { useIntersection } from '../Chart/useChartIntersection';
import BaseChart from '../common/BaseChart/BaseChart';
import ChartSvgWrapper from '../common/BaseChart/ChartSvgWrapper';
import { useComputeBaseChartDimensions } from '../common/BaseChart/useComputeBaseChartDimensions';
import { useComputeYAxisMaxCharacters } from '../common/BaseChart/useComputeYAxisMaxCharacters';
import Thresholds from '../common/Thresholds/Thresholds';
import { Thresholds as ThresholdsModel } from '../common/models';
import {
  getUnits,
  getXScale,
  getXScaleBand,
  getYScalePerUnit
} from '../common/timeSeries';
import { Line } from '../common/timeSeries/models';
import { useTooltipStyles } from '../common/useTooltipStyles';
import { computPixelsToShiftMouse } from '../common/utils';
import BarGroup from './BarGroup';
import BarChartTooltip from './Tooltip/BarChartTooltip';
import { tooltipDataAtom } from './atoms';
import { BarStyle } from './models';

interface Props
  extends Pick<
    LineChartProps,
    | 'tooltip'
    | 'legend'
    | 'axis'
    | 'header'
    | 'zoomPreview'
    | 'timeShiftZones'
    | 'annotationEvent'
  > {
  barStyle: BarStyle;
  graphData: Data;
  graphRef: MutableRefObject<HTMLDivElement | null>;
  height: number;
  limitLegend?: false | number;
  orientation: 'vertical' | 'horizontal' | 'auto';
  thresholdUnit?: string;
  thresholds?: ThresholdsModel;
  width: number;
  skipIntersectionObserver?: boolean;
  min?: number;
  max?: number;
  boundariesUnit?: string;
  start: string;
  end: string;
}

const ResponsiveBarChart = ({
  graphRef,
  graphData,
  legend,
  height,
  width,
  axis,
  thresholdUnit,
  thresholds,
  header,
  limitLegend,
  orientation,
  tooltip,
  barStyle,
  skipIntersectionObserver,
  min,
  max,
  boundariesUnit,
  start,
  end,
  timeShiftZones,
  zoomPreview,
  annotationEvent
}: Props): ReactElement => {
  const { title, timeSeries, baseAxis, lines } = graphData || {};

  const { classes, cx } = useTooltipStyles();

  const [linesGraph, setLinesGraph] = useState<Array<Line>>(lines);
  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const [tooltipData, setTooltipData] = useAtom(tooltipDataAtom);
  const isApplyingZoom = useAtomValue(applyingZoomAtomAtom);

  const { isInViewport } = useIntersection({ element: graphRef?.current });

  const displayedLines = useMemo(
    () => (linesGraph || []).filter(({ display }) => display),
    [linesGraph]
  );

  const [firstUnit, secondUnit] = getUnits(displayedLines);
  const allUnits = getUnits(lines);

  const { maxLeftAxisCharacters, maxRightAxisCharacters } =
    useComputeYAxisMaxCharacters({
      graphData,
      thresholds,
      thresholdUnit,
      axis,
      firstUnit,
      secondUnit
    });

  const { legendRef, graphWidth, graphHeight, titleRef } =
    useComputeBaseChartDimensions({
      hasSecondUnit: Boolean(secondUnit),
      height,
      legendDisplay: legend?.display,
      legendPlacement: legend?.placement,
      width,
      maxAxisCharacters: maxRightAxisCharacters || maxLeftAxisCharacters
    });

  const thresholdValues = flatten([
    pluck('value', thresholds?.warning || []),
    pluck('value', thresholds?.critical || [])
  ]);

  const isHorizontal = useMemo(() => {
    if (!equals(orientation, 'auto')) {
      return equals(orientation, 'horizontal');
    }

    return gte(graphWidth, graphHeight + 60);
  }, [orientation, graphWidth, graphHeight]);

  const xScale = useMemo(
    () =>
      getXScaleBand({
        dataTime: timeSeries,
        valueWidth: isHorizontal ? graphWidth : graphHeight - 30
      }),
    [timeSeries, graphWidth, isHorizontal, graphHeight]
  );

  const xScaleLinear = useMemo(
    () =>
      getXScale({
        dataTime: timeSeries,
        valueWidth: isHorizontal ? graphWidth : graphHeight - 30
      }),
    [timeSeries, graphWidth, isHorizontal, graphHeight]
  );

  const yScalesPerUnit = useMemo(
    () =>
      getYScalePerUnit({
        dataLines: displayedLines,
        dataTimeSeries: timeSeries,
        isCenteredZero: axis?.isCenteredZero,
        isHorizontal,
        scale: axis?.scale,
        scaleLogarithmicBase: axis?.scaleLogarithmicBase,
        thresholdUnit,
        thresholds: (thresholds?.enabled && thresholdValues) || [],
        valueGraphHeight:
          (isHorizontal ? graphHeight : graphWidth) - margin.bottom,
        min,
        max,
        isBarChart: true,
        boundariesUnit
      }),
    [
      displayedLines,
      timeSeries,
      graphHeight,
      thresholdValues,
      graphWidth,
      thresholds?.enabled,
      axis?.isCenteredZero,
      axis?.scale,
      axis?.scaleLogarithmicBase
    ]
  );

  const leftScale = yScalesPerUnit[firstUnit];
  const rightScale = yScalesPerUnit[secondUnit];
  const pixelsToShift = computPixelsToShiftMouse(xScaleLinear);

  useEffect(
    () => {
      setLinesGraph(lines);
    },
    useDeepCompare([lines])
  );

  const displayLegend = legend?.display ?? true;

  const showGridLines = useMemo(
    () => isNil(axis?.showGridLines) || axis?.showGridLines,
    [axis?.showGridLines]
  );

  const hasUnits = useMemo(() => allUnits.some(identity), [allUnits]);

  const marginTop = useMemo(
    () => (title || hasUnits ? margin.top : 0),
    [title, hasUnits]
  );

  if (!isInViewport && !skipIntersectionObserver) {
    return (
      <Skeleton
        height={graphSvgRef?.current?.clientHeight ?? graphHeight}
        variant="rectangular"
        width="100%"
      />
    );
  }

  const isTooltipHidden = equals(tooltip?.mode, 'hidden');

  return (
    <BaseChart
      base={baseAxis}
      graphWidth={graphWidth}
      header={header}
      height={height}
      isHorizontal={isHorizontal}
      legend={{
        displayLegend,
        mode: legend?.mode,
        placement: legend?.placement,
        renderExtraComponent: legend?.renderExtraComponent,
        secondaryClick: legend?.secondaryClick,
        showCalculations: legend?.showCalculations
      }}
      legendRef={legendRef}
      limitLegend={limitLegend}
      lines={linesGraph}
      setLines={setLinesGraph}
      title={title}
      titleRef={titleRef}
    >
      <Tooltip
        classes={{
          tooltip: cx(
            classes.tooltip,
            has('data', tooltipData) && classes.tooltipDisablePadding
          )
        }}
        label={
          <BarChartTooltip
            base={baseAxis}
            mode={tooltip?.mode}
            sortOrder={tooltip?.sortOrder}
            timeSeries={timeSeries}
          />
        }
        open={!equals(tooltip?.mode, 'hidden') && Boolean(tooltipData)}
        placement="top"
      >
        <div className={classes.tooltipChildren}>
          <ChartSvgWrapper
            allUnits={allUnits}
            axis={axis}
            base={baseAxis}
            displayedLines={displayedLines}
            graphHeight={graphHeight}
            graphWidth={graphWidth - (isHorizontal ? 0 : margin.left - 15)}
            gridLinesType={axis?.gridLinesType}
            leftScale={leftScale}
            orientation={isHorizontal ? 'horizontal' : 'vertical'}
            rightScale={rightScale}
            showGridLines={showGridLines}
            svgRef={graphSvgRef}
            timeSeries={timeSeries}
            xScale={xScale}
            maxAxisCharacters={maxLeftAxisCharacters}
            hasSecondUnit={Boolean(secondUnit)}
          >
            <>
              {isApplyingZoom && (
                <>
                  <BarGroup
                    barStyle={barStyle}
                    isTooltipHidden={isTooltipHidden}
                    lines={displayedLines}
                    orientation={isHorizontal ? 'horizontal' : 'vertical'}
                    size={
                      isHorizontal ? graphHeight - marginTop - 5 : graphWidth
                    }
                    timeSeries={timeSeries}
                    xScale={xScale}
                    yScalesPerUnit={yScalesPerUnit}
                    scaleType={axis?.scale}
                  />
                  {thresholds?.enabled && (
                    <Thresholds
                      displayedLines={displayedLines}
                      hideTooltip={() => setTooltipData(null)}
                      isHorizontal={isHorizontal}
                      showTooltip={({ tooltipData: thresholdLabel }) =>
                        setTooltipData({
                          thresholdLabel
                        })
                      }
                      thresholdUnit={thresholdUnit}
                      thresholds={thresholds as ThresholdsModel}
                      width={
                        isHorizontal ? graphWidth : graphHeight - marginTop
                      }
                      yScalesPerUnit={yScalesPerUnit}
                    />
                  )}
                </>
              )}
              {isHorizontal && (
                <InteractionWithGraph
                  additionalZoomMargin={pixelsToShift}
                  maxLeftAxisCharacters={maxLeftAxisCharacters}
                  commonData={{
                    graphHeight,
                    graphSvgRef,
                    graphWidth,
                    lines,
                    xScale: xScaleLinear,
                    timeSeries,
                    yScalesPerUnit
                  }}
                  annotationData={{ ...annotationEvent }}
                  zoomData={{ ...zoomPreview }}
                  timeShiftZonesData={{
                    ...timeShiftZones,
                    graphInterval: { start, end }
                  }}
                />
              )}
              {!isApplyingZoom && (
                <>
                  <BarGroup
                    barStyle={barStyle}
                    isTooltipHidden={isTooltipHidden}
                    lines={displayedLines}
                    orientation={isHorizontal ? 'horizontal' : 'vertical'}
                    size={
                      isHorizontal ? graphHeight - marginTop - 5 : graphWidth
                    }
                    timeSeries={timeSeries}
                    xScale={xScale}
                    yScalesPerUnit={yScalesPerUnit}
                    scaleType={axis?.scale}
                  />
                  {thresholds?.enabled && (
                    <Thresholds
                      displayedLines={displayedLines}
                      hideTooltip={() => setTooltipData(null)}
                      isHorizontal={isHorizontal}
                      showTooltip={({ tooltipData: thresholdLabel }) =>
                        setTooltipData({
                          thresholdLabel
                        })
                      }
                      thresholdUnit={thresholdUnit}
                      thresholds={thresholds as ThresholdsModel}
                      width={
                        isHorizontal ? graphWidth : graphHeight - marginTop
                      }
                      yScalesPerUnit={yScalesPerUnit}
                    />
                  )}
                </>
              )}
            </>
          </ChartSvgWrapper>
        </div>
      </Tooltip>
    </BaseChart>
  );
};

export default ResponsiveBarChart;
