import { MutableRefObject, useEffect, useMemo, useRef, useState } from 'react';

import { equals, flatten, isNil, pluck, reject } from 'ramda';
import { useAtom } from 'jotai';

import { ClickAwayListener, Skeleton } from '@mui/material';

import { getUnits, getXScale, getYScalePerUnit } from '../common/timeSeries';
import { Line } from '../common/timeSeries/models';
import { Thresholds as ThresholdsModel } from '../common/models';
import BaseChart from '../common/BaseChart/BaseChart';
import { useComputeBaseChartDimensions } from '../common/BaseChart/useComputeBaseChartDimensions';
import ChartSvgWrapper from '../common/BaseChart/ChartSvgWrapper';
import Thresholds from '../common/Thresholds/Thresholds';
import { useDeepCompare } from '../../utils';

import Lines from './BasicComponents/Lines';
import {
  canDisplayThreshold,
  findLineOfOriginMetricThreshold,
  lowerLineName,
  upperLineName
} from './BasicComponents/Lines/Threshold/models';
import InteractionWithGraph from './InteractiveComponents';
import GraphTooltip from './InteractiveComponents/Tooltip';
import useGraphTooltip from './InteractiveComponents/Tooltip/useGraphTooltip';
import { margin } from './common';
import { Data, GlobalAreaLines, GraphInterval, LineChartProps } from './models';
import { useIntersection } from './useChartIntersection';
import { useChartStyles } from './Chart.styles';
import GraphValueTooltip from './InteractiveComponents/GraphValueTooltip/GraphValueTooltip';
import { thresholdTooltipAtom } from './graphAtoms';

interface Props extends LineChartProps {
  graphData: Data;
  graphInterval: GraphInterval;
  graphRef: MutableRefObject<HTMLDivElement | null>;
  limitLegend?: false | number;
  shapeLines?: GlobalAreaLines;
  thresholdUnit?: string;
  thresholds?: ThresholdsModel;
}

const filterLines = (lines: Array<Line>, displayThreshold): Array<Line> => {
  if (!displayThreshold) {
    return lines;
  }
  const lineOriginMetric = findLineOfOriginMetricThreshold(lines);

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
  thresholds,
  thresholdUnit,
  limitLegend
}: Props): JSX.Element => {
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

  const yScalesPerUnit = useMemo(
    () =>
      getYScalePerUnit({
        dataLines: displayedLines,
        dataTimeSeries: timeSeries,
        isCenteredZero: axis?.isCenteredZero,
        scale: axis?.scale,
        scaleLogarithmicBase: axis?.scaleLogarithmicBase,
        thresholdUnit,
        thresholds: (thresholds?.enabled && thresholdValues) || [],
        valueGraphHeight: graphHeight - margin.bottom
      }),
    [
      displayedLines,
      timeSeries,
      graphHeight,
      thresholdValues,
      thresholds?.enabled,
      axis?.isCenteredZero,
      axis?.scale,
      axis?.scaleLogarithmicBase
    ]
  );

  const leftScale = yScalesPerUnit[firstUnit];
  const rightScale = yScalesPerUnit[secondUnit];

  useEffect(
    () => {
      setLinesGraph(
        filterLines(lines, canDisplayThreshold(shapeLines?.areaThresholdLines))
      );
    },
    useDeepCompare([lines])
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
    <ClickAwayListener onClickAway={graphTooltipData?.hideTooltip}>
      <>
        <BaseChart
          base={baseAxis}
          graphWidth={graphWidth}
          header={header}
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
          lines={linesGraph}
          setLines={setLinesGraph}
          title={title}
        >
          <GraphValueTooltip
            baseAxis={baseAxis}
            thresholdTooltip={thresholdTooltip}
            tooltip={tooltip}
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
                    lineWidth={lineStyle?.lineWidth}
                    showArea={lineStyle?.showArea}
                    showPoints={lineStyle?.showPoints}
                    timeSeries={timeSeries}
                    width={graphWidth}
                    xScale={xScale}
                    yScalesPerUnit={yScalesPerUnit}
                    {...shapeLines}
                  />
                  <InteractionWithGraph
                    annotationData={{ ...annotationEvent }}
                    commonData={{
                      graphHeight,
                      graphSvgRef,
                      graphWidth,
                      lines: linesGraph,
                      timeSeries,
                      xScale,
                      yScalesPerUnit
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
                      hideTooltip={() => setThresholdTooltip(null)}
                      showTooltip={({ tooltipData: thresholdLabel }) =>
                        setThresholdTooltip({
                          thresholdLabel
                        })
                      }
                      thresholdUnit={thresholdUnit}
                      thresholds={thresholds as ThresholdsModel}
                      width={graphWidth}
                      yScalesPerUnit={yScalesPerUnit}
                    />
                  )}
                </>
              </ChartSvgWrapper>
            </div>
          </GraphValueTooltip>
        </BaseChart>
        {displayTooltip && <GraphTooltip {...tooltip} {...graphTooltipData} />}
      </>
    </ClickAwayListener>
  );
};

export default Chart;
