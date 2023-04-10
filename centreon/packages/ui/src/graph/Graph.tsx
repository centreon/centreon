import { useMemo, useRef } from 'react';

import { Group } from '@visx/visx';
import { difference, isNil } from 'ramda';

import {
  getLeftScale,
  getLineData,
  getNotInvertedStackedLines,
  getRightScale,
  getSortedStackedLines,
  getStackedYScale,
  getTimeSeries,
  getTimeSeriesForLines,
  getXScale
} from '../timeSeries';
import { Line } from '../timeSeries/models';

import Axes from './axes';
import { margin } from './common';
import Grids from './grids';
import Lines from './lines';

interface RegularLineData {
  [x: string]: unknown;
  lines?: Array<Line>;
}
interface ShapeLines {
  areaRegularLinesData?: RegularLineData;
  areaStackData?: any;
  displayAreaRegularLines?: boolean;
  displayAreaStack?: boolean;
}

const defaultShapeLines = {
  displayAreaRegularLines: true,
  displayAreaStack: true
};

interface Props {
  graphData: any;
  height: number;
  shapeLines?: ShapeLines;
  width: number;
}

const Graph = ({
  graphData,
  height,
  width,
  shapeLines = defaultShapeLines
}: Props): JSX.Element => {
  const containerRef = useRef<SVGSVGElement | null>(null);
  const timeSeries = getTimeSeries(graphData);
  const lines = getLineData(graphData);
  const graphWidth = width > 0 ? width - margin.left - margin.right : 0;
  const graphHeight = height > 0 ? height - margin.top - margin.bottom : 0;

  const xScale = useMemo(
    () =>
      getXScale({
        dataTime: timeSeries,
        valueWidth: graphWidth
      }),
    [graphWidth, timeSeries]
  );

  const leftScale = useMemo(
    () =>
      getLeftScale({
        dataLines: lines,
        dataTimeSeries: timeSeries,
        valueGraphHeight: graphHeight
      }),
    [timeSeries, lines, graphHeight]
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

  const { displayAreaRegularLines, displayAreaStack } = shapeLines;

  const regularStackedLines = getNotInvertedStackedLines(lines);

  const regularStackedTimeSeries = getTimeSeriesForLines({
    lines: regularStackedLines,
    timeSeries
  });

  const stackedYScale = getStackedYScale({ leftScale, rightScale });

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
        />
        <Axes
          data={{
            graphData,
            lines,
            timeSeries
          }}
          height={graphHeight}
          leftScale={leftScale}
          rightScale={rightScale}
          width={graphWidth}
          xScale={xScale}
        />
        <Lines
          data={{ lines, timeSeries }}
          height={graphHeight}
          leftScale={leftScale}
          rightScale={rightScale}
          shape={{
            areaRegularLines: {
              displayAreaRegularLines,
              lines: regularLines,
              ...shapeLines?.areaRegularLinesData
            },
            areaStack: {
              // displayAreaStack,
              lines: regularStackedLines,
              timeSeries: regularStackedTimeSeries,
              timeTick: null,
              yScale: stackedYScale
            }
          }}
          xScale={xScale}
        />
      </Group.Group>
    </svg>
  );
};

export default Graph;
