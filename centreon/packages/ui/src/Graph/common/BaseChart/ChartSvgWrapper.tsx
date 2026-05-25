import { Group } from '@visx/visx';
import type { ScaleBand, ScaleLinear, ScaleTime } from 'd3-scale';
import { equals } from 'ramda';
import type { MutableRefObject, ReactElement, ReactNode } from 'react';

import { margin } from '../../Chart/common';
import type { ChartAxis } from '../../Chart/models';
import Axes from '../Axes';
import Grids from '../Grids';
import type { Line, TimeValue } from '../timeSeries/models';
import { useMarginTop } from '../useMarginTop';
import { computeGElementMarginLeft } from '../utils';

interface Props {
  allUnits: Array<string>;
  axis?: ChartAxis;
  base?: number;
  children: ReactNode;
  displayedLines: Array<Line>;
  graphHeight: number;
  graphWidth: number;
  gridLinesType?: string;
  leftScale: ScaleLinear<number, number>;
  orientation?: 'horizontal' | 'vertical';
  rightScale: ScaleLinear<number, number>;
  showGridLines: boolean;
  svgRef: MutableRefObject<SVGSVGElement | null>;
  timeSeries: Array<TimeValue>;
  xScale:
    | ScaleTime<number, number>
    | ScaleLinear<number, number>
    | ScaleBand<number>;
  maxAxisCharacters?: number;
  hasSecondUnit?: boolean;
  title?: string;
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
  orientation = 'horizontal',
  allUnits,
  maxAxisCharacters = 0,
  hasSecondUnit,
  title
}: Props): ReactElement => {
  const isHorizontal = equals(orientation, 'horizontal');
  const hasValidLeftScale = Boolean(leftScale);
  const hasValidXScale = Boolean(xScale);
  const canRenderAxes = hasValidLeftScale && hasValidXScale;
  const canRenderGridRows = Boolean(isHorizontal ? leftScale : xScale);
  const canRenderGridColumns = Boolean(isHorizontal ? xScale : leftScale);

  const marginTop = useMarginTop({ title, units: allUnits });

  return (
    <svg
      aria-label="graph"
      height={graphHeight + marginTop}
      ref={svgRef}
      width="100%"
    >
      <title>chart</title>
      <Group.Group
        left={computeGElementMarginLeft({
          hasSecondUnit,
          maxCharacters: maxAxisCharacters
        })}
        top={marginTop}
      >
        {showGridLines && (canRenderGridRows || canRenderGridColumns) && (
          <Grids
            // @ts-expect-error - suppressing pre-existing type mismatch
            gridLinesType={gridLinesType}
            height={graphHeight - margin.bottom}
            leftScale={isHorizontal ? leftScale : xScale}
            width={graphWidth}
            xScale={isHorizontal ? xScale : leftScale}
          />
        )}
        {canRenderAxes && (
          <Axes
            allUnits={allUnits}
            data={{
              baseAxis: base,
              lines: displayedLines,
              timeSeries,
              ...axis
            }}
            height={graphHeight}
            leftScale={leftScale}
            orientation={orientation}
            rightScale={rightScale}
            width={graphWidth}
            xScale={xScale}
          />
        )}
        {children}
      </Group.Group>
    </svg>
  );
};

export default ChartSvgWrapper;
