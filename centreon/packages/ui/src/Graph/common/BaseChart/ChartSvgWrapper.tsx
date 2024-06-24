import { MutableRefObject } from 'react';

import { Group } from '@visx/visx';
import { equals } from 'ramda';

import { margin } from '../../LineChart/common';
import { LineChartAxis } from '../../LineChart/models';
import Axes from '../Axes';
import Grids from '../Grids';
import { Line, TimeValue } from '../timeSeries/models';

import { extraMargin } from './useComputeBaseChartDimensions';

interface Props {
  axis?: LineChartAxis;
  base?: number;
  children: JSX.Element;
  displayedLines: Array<Line>;
  graphHeight: number;
  graphWidth: number;
  gridLinesType?: string;
  leftScale;
  orientation?: 'horizontal' | 'vertical';
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
  orientation = 'horizontal'
}: Props): JSX.Element => {
  const isHorizontal = equals(orientation, 'horizontal');

  return (
    <svg
      aria-label="graph"
      height={graphHeight + margin.top}
      ref={svgRef}
      width="100%"
    >
      <Group.Group left={margin.left + extraMargin / 2} top={margin.top}>
        {showGridLines && (
          <Grids
            gridLinesType={gridLinesType}
            height={graphHeight - margin.top}
            leftScale={isHorizontal ? leftScale : xScale}
            width={graphWidth}
            xScale={isHorizontal ? xScale : leftScale}
          />
        )}
        <Axes
          data={{
            baseAxis: base,
            lines: displayedLines,
            timeSeries,
            ...axis
          }}
          height={graphHeight - margin.top}
          leftScale={leftScale}
          orientation={orientation}
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
