import { Axis } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { equals, isNil } from 'ramda';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import { margin } from '../../Chart/common';
import { getXAxisTickFormat } from '../../Chart/helpers';
import { getUnits } from '../timeSeries';

import UnitLabel from './UnitLabel';
import { Data } from './models';
import useAxisY from './useAxisY';

interface Props {
  allUnits: Array<string>;
  data: Data;
  height: number;
  leftScale: ScaleLinear<number, number>;
  orientation: 'horizontal' | 'vertical';
  rightScale: ScaleLinear<number, number>;
  width: number;
  xScale: ScaleLinear<number, number>;
}

const Axes = ({
  height,
  width,
  data,
  rightScale,
  leftScale,
  xScale,
  orientation,
  allUnits
}: Props): JSX.Element => {
  const { format } = useLocaleDateTimeFormat();
  const { lines, showBorder, yAxisTickLabelRotation } = data;
  const isHorizontal = equals(orientation, 'horizontal');

  const { axisLeft, axisRight } = useAxisY({
    data,
    graphHeight: height,
    graphWidth: width,
    isHorizontal
  });

  const [, secondUnit] = getUnits(lines);

  const xTickCount = Math.min(Math.ceil(width / 82), 12);

  const [start, end] = xScale.domain();

  const tickFormat =
    data?.axisX?.xAxisTickFormat ?? getXAxisTickFormat({ end, start });

  const formatAxisTick = (tick): string =>
    format({ date: new Date(tick), formatString: tickFormat });

  const displayAxisRight = !isNil(secondUnit);

  const AxisBottom = isHorizontal ? Axis.AxisBottom : Axis.AxisLeft;
  const AxisLeft = isHorizontal ? Axis.AxisLeft : Axis.AxisTop;
  const AxisRight = isHorizontal ? Axis.AxisRight : Axis.AxisBottom;

  return (
    <g>
      <AxisBottom
        numTicks={xTickCount}
        scale={xScale}
        strokeWidth={!isNil(showBorder) && !showBorder ? 0 : 1}
        tickFormat={formatAxisTick}
        tickLabelProps={() => ({
          ...axisLeft.tickLabelProps(),
          dx: isHorizontal ? 16 : -4
        })}
        top={isHorizontal ? height - margin.bottom : 0}
      />

      {axisLeft.displayUnit && (
        <UnitLabel
          unit={axisLeft.unit}
          units={allUnits}
          x={isHorizontal ? -8 : width + 8}
          y={isHorizontal ? 16 : -2}
          onUnitChange={data.axisYLeft?.onUnitChange}
        />
      )}

      <AxisLeft
        numTicks={axisLeft?.numTicks}
        scale={leftScale}
        strokeWidth={!isNil(showBorder) && !showBorder ? 0 : 1}
        tickFormat={axisLeft.tickFormat}
        tickLabelProps={() => ({
          ...axisLeft.tickLabelProps(),
          angle: yAxisTickLabelRotation,
          dx: isHorizontal ? -8 : 4,
          dy: isHorizontal ? 4 : -6
        })}
        tickLength={2}
      />

      {displayAxisRight && (
        <AxisRight
          left={isHorizontal ? width : 0}
          numTicks={axisRight?.numTicks}
          scale={rightScale}
          strokeWidth={!isNil(showBorder) && !showBorder ? 0 : 1}
          tickFormat={axisRight.tickFormat}
          tickLabelProps={() => ({
            ...axisRight.tickLabelProps(),
            angle: yAxisTickLabelRotation,
            dx: isHorizontal ? 4 : -4,
            dy: 4
          })}
          tickLength={2}
          top={isHorizontal ? 0 : height - margin.bottom}
        />
      )}
      {axisRight.displayUnit && (
        <UnitLabel
          unit={axisRight.unit}
          units={allUnits}
          x={width}
          y={isHorizontal ? 16 : -(height + 8)}
          onUnitChange={data.axisYRight?.onUnitChange}
        />
      )}
    </g>
  );
};

export default Axes;
