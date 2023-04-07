import { useMemo, useRef } from 'react';

import { Group } from '@visx/visx';

import {
  getLeftScale,
  getLineData,
  getRightScale,
  getTimeSeries,
  getXScale
} from '../timeSeries';

import Grids from './grids';
import Axes from './axes';
import { margin } from './common';

interface Props {
  graphData: any;
  height: any;
  width: any;
}

const Graph = ({ graphData, height, width }: Props): JSX.Element => {
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
      </Group.Group>
    </svg>
  );
};

export default Graph;
