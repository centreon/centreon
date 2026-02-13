import { ClickAwayListener, Skeleton } from '@mui/material';

import { useAtom } from 'jotai';
import { equals, flatten, isEmpty, isNil, pluck, reject } from 'ramda';
import {
  type MutableRefObject,
  type ReactElement,
  useEffect,
  useMemo,
  useRef,
  useState
} from 'react';

import { useDeepCompare } from '../../utils';
import BarGroup from '../BarChart/BarGroup';
import AdditionalLine from '../common/BaseChart/AdditionalLine';
import BaseChart from '../common/BaseChart/BaseChart';
import ChartSvgWrapper from '../common/BaseChart/ChartSvgWrapper';
import { useComputeBaseChartDimensions } from '../common/BaseChart/useComputeBaseChartDimensions';
import { useComputeYAxisMaxCharacters } from '../common/BaseChart/useComputeYAxisMaxCharacters';
import type { Thresholds as ThresholdsModel } from '../common/models';
import Thresholds from '../common/Thresholds/Thresholds';
import {
  getUnits,
  getXScale,
  getXScaleBand,
  getYScalePerUnit
} from '../common/timeSeries';
import type { Line } from '../common/timeSeries/models';
import { useMarginTop } from '../common/useMarginTop';
import Lines from './BasicComponents/Lines';
import {
  canDisplayThreshold,
  findLineOfOriginMetricThreshold,
  lowerLineName,
  upperLineName
} from './BasicComponents/Lines/Threshold/models';
import { useChartStyles } from './Chart.styles';
import { margin } from './common';
import { thresholdTooltipAtom } from './graphAtoms';
import InteractionWithGraph from './InteractiveComponents';
import GraphValueTooltip from './InteractiveComponents/GraphValueTooltip/GraphValueTooltip';
import GraphTooltip from './InteractiveComponents/Tooltip';
import useGraphTooltip from './InteractiveComponents/Tooltip/useGraphTooltip';
import type {
  Data,
  GlobalAreaLines,
  GraphInterval,
  LineChartProps
} from './models';
import { useIntersection } from './useChartIntersection';

interface Props extends LineChartProps {
  graphData: Data;
  graphInterval: GraphInterval;
  graphRef: MutableRefObject<HTMLDivElement | null>;
  limitLegend?: false | number;
  shapeLines?: GlobalAreaLines;
  thresholdUnit?: string;
  thresholds?: ThresholdsModel;
  transformMatrix?: {
    fx?: (pointX: number) => number;
    fy?: (pointY: number) => number;
  };
}

const filterLines = (
  lines: Array<Line>,
  displayThreshold: boolean
): Array<Line> => {
  if (!displayThreshold) {
    return lines;
  }
  const lineOriginMetric = findLineOfOriginMetricThreshold(lines);

  if (isEmpty(lineOriginMetric)) {
    return lines;
  }

  const findLinesUpperLower = lines.map((line) =>
    equals(line.name, lowerLineName) || equals(line.name, upperLineName)
      ? line
      : null
  );

  const linesUpperLower = reject((element) => !element, findLinesUpperLower);

  return [...lineOriginMetric, ...linesUpperLower] as Array<Line>;
};

const Chart = ({
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
  barStyle = {
    opacity: 1,
    radius: 0.2
  },
  thresholds,
  thresholdUnit,
  limitLegend,
  skipIntersectionObserver,
  transformMatrix,
  additionalLines,
  min,
  max,
  boundariesUnit
}: Props): ReactElement => {
  const { classes } = useChartStyles();

  const { title, timeSeries, baseAxis, lines } = graphData;

  const [linesGraph, setLinesGraph] = useState<Array<Line>>(
    filterLines(lines, canDisplayThreshold(shapeLines?.areaThresholdLines))
  );

  const graphSvgRef = useRef<SVGSVGElement | null>(null);

  const [thresholdTooltip, setThresholdTooltip] = useAtom(thresholdTooltipAtom);

  const { isInViewport } = useIntersection({ element: graphRef?.current });

  const thresholdValues = flatten([
    pluck('value', thresholds?.warning || []),
    pluck('value', thresholds?.critical || [])
  ]);

  const displayedLines = useMemo(
    () => linesGraph.filter(({ display }) => display),
    [linesGraph]
  );
  const [firstUnit, secondUnit] = useMemo(
    () => getUnits(displayedLines),
    [displayedLines]
  );

  const { maxLeftAxisCharacters, maxRightAxisCharacters } =
    useComputeYAxisMaxCharacters({
      axis,
      firstUnit,
      graphData,
      secondUnit,
      thresholds,
      thresholdUnit
    });

  const allUnits = getUnits(linesGraph);

  const { legendRef, graphWidth, graphHeight, titleRef } =
    useComputeBaseChartDimensions({
      hasSecondUnit: Boolean(secondUnit),
      height,
      legendDisplay: legend?.display,
      legendHeight: legend?.height,
      legendPlacement: legend?.placement,
      maxAxisCharacters: maxRightAxisCharacters || maxLeftAxisCharacters,
      title,
      units: allUnits,
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

  const xScaleBand = useMemo(
    () =>
      getXScaleBand({
        dataTime: timeSeries,
        valueWidth: graphWidth
      }),
    [timeSeries, graphWidth]
  );

  const yScalesPerUnit = useMemo(
    () =>
      getYScalePerUnit({
        boundariesUnit,
        dataLines: linesGraph,
        dataTimeSeries: timeSeries,
        isCenteredZero: axis?.isCenteredZero,
        isFilled: lineStyle?.showArea,
        max,
        min,
        scale: axis?.scale,
        scaleLogarithmicBase: axis?.scaleLogarithmicBase,
        thresholds: (thresholds?.enabled && thresholdValues) || [],
        thresholdUnit,
        valueGraphHeight: graphHeight - margin.bottom
      }),
    [
      linesGraph,
      timeSeries,
      graphHeight,
      thresholdValues,
      thresholds?.enabled,
      axis?.isCenteredZero,
      axis?.scale,
      axis?.scaleLogarithmicBase,
      boundariesUnit,
      lineStyle?.showArea,
      max,
      min,
      thresholdUnit
    ]
  );

  const leftScale = yScalesPerUnit[axis?.axisYLeft?.unit ?? firstUnit];
  const rightScale = yScalesPerUnit[axis?.axisYRight?.unit ?? secondUnit];

  const linesDisplayedAsLine = useMemo(
    () =>
      displayedLines.filter(
        ({ displayAs }) => isNil(displayAs) || equals(displayAs, 'line')
      ),
    [displayedLines]
  );

  const linesDisplayedAsBar = useMemo(
    () => displayedLines.filter(({ displayAs }) => equals(displayAs, 'bar')),
    [displayedLines]
  );

  useEffect(() => {
    setLinesGraph(
      filterLines(lines, canDisplayThreshold(shapeLines?.areaThresholdLines))
    );
  }, [
    ...useDeepCompare([lines, shapeLines?.areaThresholdLines]),
    lines,
    shapeLines?.areaThresholdLines
  ]);

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

  const hasSecondUnit = useMemo(() => Boolean(secondUnit), [secondUnit]);

  const marginTop = useMarginTop({ title, units: allUnits });

  if ((!isInViewport && !skipIntersectionObserver) || !height) {
    return (
      <Skeleton
        height={graphSvgRef?.current?.clientHeight ?? graphHeight}
        variant="rectangular"
        width="100%"
      />
    );
  }

  return (
    <ClickAwayListener onClickAway={graphTooltipData?.hideTooltip}>
      <div className={classes.baseWrapper}>
        <BaseChart
          base={baseAxis}
          graphHeight={graphHeight}
          graphWidth={graphWidth}
          header={header}
          height={height}
          legend={{
            ...legend,
            displayLegend,
            legendHeight: legend?.height,
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
          <GraphValueTooltip
            baseAxis={baseAxis}
            thresholdTooltip={thresholdTooltip}
            tooltip={tooltip}
          >
            <div className={classes.tooltipChildren}>
              <ChartSvgWrapper
                allUnits={allUnits}
                axis={axis}
                base={baseAxis}
                displayedLines={displayedLines}
                graphHeight={graphHeight}
                graphWidth={graphWidth}
                gridLinesType={axis?.gridLinesType}
                hasSecondUnit={hasSecondUnit}
                leftScale={leftScale}
                maxAxisCharacters={maxLeftAxisCharacters}
                rightScale={rightScale}
                showGridLines={showGridLines}
                svgRef={graphSvgRef}
                timeSeries={timeSeries}
                xScale={xScale}
              >
                {!isEmpty(linesDisplayedAsBar) && (
                  <BarGroup
                    barStyle={barStyle}
                    isTooltipHidden={false}
                    lines={linesDisplayedAsBar}
                    orientation="horizontal"
                    size={graphHeight - marginTop - 5}
                    timeSeries={timeSeries}
                    xScale={xScaleBand}
                    yScalesPerUnit={yScalesPerUnit}
                  />
                )}
                {!isEmpty(linesDisplayedAsLine) && (
                  <Lines
                    axis={axis}
                    displayAnchor={displayAnchor}
                    displayedLines={linesDisplayedAsLine}
                    firstUnit={firstUnit}
                    graphSvgRef={graphSvgRef}
                    hasSecondUnit={hasSecondUnit}
                    height={graphHeight - marginTop}
                    lineStyle={lineStyle}
                    maxLeftAxisCharacters={maxLeftAxisCharacters}
                    scale={axis?.scale}
                    scaleLogarithmicBase={axis?.scaleLogarithmicBase}
                    secondUnit={secondUnit}
                    timeSeries={timeSeries}
                    width={graphWidth}
                    xScale={xScale}
                    yScalesPerUnit={yScalesPerUnit}
                    {...shapeLines}
                  />
                )}
                {additionalLines?.map((additionalLine) => (
                  <AdditionalLine
                    key={additionalLine.yValue}
                    {...additionalLine}
                    graphWidth={graphWidth}
                    yScale={yScalesPerUnit[additionalLine.unit]}
                  />
                ))}
                <InteractionWithGraph
                  annotationData={{ ...annotationEvent }}
                  commonData={{
                    graphHeight,
                    graphSvgRef,
                    graphWidth,
                    lines: displayedLines,
                    timeSeries,
                    xScale,
                    yScalesPerUnit
                  }}
                  hasSecondUnit={hasSecondUnit}
                  maxLeftAxisCharacters={maxLeftAxisCharacters}
                  timeShiftZonesData={{
                    ...timeShiftZones,
                    graphInterval
                  }}
                  transformMatrix={transformMatrix}
                  zoomData={{ ...zoomPreview }}
                />
                {thresholds?.enabled && (
                  <Thresholds
                    displayedLines={displayedLines}
                    hideTooltip={() => setThresholdTooltip(null)}
                    showTooltip={({ tooltipData: thresholdLabel }) =>
                      setThresholdTooltip({
                        thresholdLabel
                      })
                    }
                    thresholds={thresholds as ThresholdsModel}
                    thresholdUnit={thresholdUnit}
                    width={graphWidth}
                    yScalesPerUnit={yScalesPerUnit}
                  />
                )}
              </ChartSvgWrapper>
            </div>
          </GraphValueTooltip>
        </BaseChart>
        {displayTooltip && <GraphTooltip {...tooltip} {...graphTooltipData} />}
      </div>
    </ClickAwayListener>
  );
};

export default Chart;
