import { useMemo, useRef } from 'react';

import { Group } from '@visx/visx';
import { difference } from 'ramda';

import {
  getLeftScale,
  getLineData,
  getRightScale,
  getSortedStackedLines,
  getTimeSeries,
  getXScale
} from '../timeSeries';
import { Line } from '../timeSeries/models';

import Axes from './Axes';
import Grids from './Grids';
import Lines from './Lines';
import useStackedLines from './Lines/StackedLines/useStackedLines';
import { margin } from './common';
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

  const { regularStackedLines, invertedStackedLines } = useStackedLines({
    lines,
    timeSeries
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
        dataLines: lines,
        dataTimeSeries: timeSeries,
        valueGraphHeight: graphHeight
      }),
    [lines, timeSeries, graphHeight]
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

  const regularLines = (): Array<Line> => {
    const stackedLines = getSortedStackedLines(lines);

    return difference(lines, stackedLines);
  };

  const commonLinesProps = {
    display: true,
    leftScale,
    rightScale,
    xScale
  };

  const areaRegularLines = {
    lines: regularLines(),
    timeSeries,
    ...commonLinesProps,
    ...shapeLines?.areaRegularLinesData
  };

  const regularStackedLinesData = {
    lines: regularStackedLines.lines,
    timeSeries: regularStackedLines.timeSeries
  };

  const invertedStackedLinesData = {
    lines: invertedStackedLines.lines,
    timeSeries: invertedStackedLines.timeSeries
  };

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
            areaRegularLines,
            areaStackedLines: {
              ...commonLinesProps,
              invertedStackedLinesData,
              regularStackedLinesData,
              ...shapeLines?.areaStackedLinesData
            }
          }}
        />
      </Group.Group>
    </svg>
  );
};

export default Graph;
