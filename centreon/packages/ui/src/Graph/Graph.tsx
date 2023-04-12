import { useMemo, useRef } from 'react';

import { Group } from '@visx/visx';
import { difference } from 'ramda';

import {
  getLeftScale,
  getLineData,
  getNotInvertedStackedLines,
  getRightScale,
  getSortedStackedLines,
  getTimeSeries,
  getTimeSeriesForLines,
  getXScale
} from '../timeSeries';

import Axes from './Axes';
import { margin } from './common';
import Grids from './Grids';
import Lines from './Lines';
import { Axis, Data, GraphData, GridsModel, ShapeLines } from './models';

interface Props {
  axis?: Axis;
  graphData: GraphData | Data;
  grids?: GridsModel;
  height: number;
  shapeLines?: ShapeLines;
  width: number;
}

const Graph = ({
  graphData,
  height,
  width,
  shapeLines,
  axis,
  grids
}: Props): JSX.Element => {
  const containerRef = useRef<SVGSVGElement | null>(null);

  const graphWidth = width > 0 ? width - margin.left - margin.right : 0;
  const graphHeight = height > 0 ? height - margin.top - margin.bottom : 0;

  const timeSeries =
    'timeSeries' in graphData ? graphData.timeSeries : getTimeSeries(graphData);
  const lines = 'lines' in graphData ? graphData.lines : getLineData(graphData);
  const baseAxis =
    'baseAxis' in graphData ? graphData.baseAxis : graphData.global?.base;

  const xScale = getXScale({
    dataTime: timeSeries,
    valueWidth: graphWidth
  });

  const leftScale = getLeftScale({
    dataLines: lines,
    dataTimeSeries: timeSeries,
    valueGraphHeight: graphHeight
  });

  const rightScale = getRightScale({
    dataLines: lines,
    dataTimeSeries: timeSeries,
    valueGraphHeight: graphHeight
  });

  const regularStackedLines = getNotInvertedStackedLines(lines);

  const regularStackedTimeSeries = getTimeSeriesForLines({
    lines: regularStackedLines,
    timeSeries
  });

  const stackedLines = getSortedStackedLines(lines);

  const regularLines = difference(lines, stackedLines);

  return (
    <svg height={height} ref={containerRef} width="100%">
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
          height={graphHeight}
          leftScale={leftScale}
          rightScale={rightScale}
          width={graphWidth}
          xScale={xScale}
        />
        <Lines
          height={graphHeight}
          shape={{
            areaRegularLines: {
              leftScale,
              lines: regularLines,
              rightScale,
              timeSeries,
              xScale,
              ...shapeLines?.areaRegularLinesData
            },
            areaStackedLines: {
              leftScale,
              lines: regularStackedLines,
              rightScale,
              timeSeries: regularStackedTimeSeries,
              xScale,
              ...shapeLines?.areaStackedLinesData
            }
          }}
        />
      </Group.Group>
    </svg>
  );
};

export default Graph;
