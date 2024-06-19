import { MutableRefObject } from 'react';

import { Group } from '@visx/visx';

import { margin } from '../../LineChart/common';
import Grids from '../Grids';
import Axes from '../Axes';
import { Line, TimeValue } from '../timeSeries/models';
import { GraphInterval, LineChartAxis } from '../../LineChart/models';

import { extraMargin } from './useComputeBaseChartDimensions';

interface Props {
  axis?: LineChartAxis;
  base?: number;
  children: JSX.Element;
  displayedLines: Array<Line>;
  graphHeight: number;
  graphInterval: GraphInterval;
  graphWidth: number;
  gridLinesType?: string;
  leftScale;
  rightScale;
  showGridLines: boolean;
  svgRef: MutableRefObject<SVGSVGElement | null>;
  timeSeries: Array<TimeValue>;
  xScale;
}

const ChartSvgWrapper = ({
  svgRef,
  graphHeight,
  leftScale,
  rightScale,
  xScale,
  graphWidth,
  showGridLines,
  gridLinesType,
  base = 1000,
  displayedLines,
  timeSeries,
  axis,
  children,
  graphInterval
}: Props): JSX.Element => {
  return (
    <svg height={graphHeight + margin.top} ref={svgRef} width="100%">
      <Group.Group left={margin.left + extraMargin / 2} top={margin.top}>
        {showGridLines && (
          <Grids
            gridLinesType={gridLinesType}
            height={graphHeight - margin.top}
            leftScale={leftScale}
            width={graphWidth}
            xScale={xScale}
          />
        )}
        <Axes
          data={{
            baseAxis: base,
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
        {children}
      </Group.Group>
    </svg>
  );
};

export default ChartSvgWrapper;
