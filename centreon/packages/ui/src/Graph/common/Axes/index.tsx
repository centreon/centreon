import { Axis } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { equals, isNil } from 'ramda';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import { getXAxisTickFormat } from '../../LineChart/helpers';
import { getUnits } from '../timeSeries';

import UnitLabel from './UnitLabel';
import { Data } from './models';
import useAxisY from './useAxisY';

interface Props {
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
  orientation
}: Props): JSX.Element => {
  const { format } = useLocaleDateTimeFormat();
  const { lines, showBorder, yAxisTickLabelRotation } = data;

  const { axisLeft, axisRight } = useAxisY({ data, graphHeight: height });

  const [firstUnit, secondUnit, thirdUnit] = getUnits(lines);

  const xTickCount = Math.min(Math.ceil(width / 82), 12);

  const tickFormat =
    data?.axisX?.xAxisTickFormat ??
    getXAxisTickFormat({
      start: xScale.domain()[0],
      start: xScale.domain()[-1]
    });

  const formatAxisTick = (tick): string =>
    format({ date: new Date(tick), formatString: tickFormat });

  const hasMoreThanTwoUnits = !isNil(thirdUnit);
  const displayAxisRight = !isNil(secondUnit) && !hasMoreThanTwoUnits;

  const isHorizontal = equals(orientation, 'horizontal');

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
        top={isHorizontal ? height - 5 : 0}
      />

      {axisLeft.displayUnit && <UnitLabel unit={firstUnit} x={-4} />}

      <AxisLeft
        numTicks={axisLeft?.numTicks}
        scale={leftScale}
        strokeWidth={!isNil(showBorder) && !showBorder ? 0 : 1}
        tickFormat={axisLeft.tickFormat}
        tickLabelProps={() => ({
          ...axisLeft.tickLabelProps(),
          angle: yAxisTickLabelRotation,
          dx: isHorizontal ? -8 : 2,
          dy: isHorizontal ? 2 : -4
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
            dx: isHorizontal ? -8 : -8,
            dy: isHorizontal ? 2 : 4
          })}
          tickLength={2}
          top={isHorizontal ? 0 : height}
        />
      )}
      {axisRight.displayUnit && <UnitLabel unit={secondUnit} x={width} />}
    </g>
  );
};

export default Axes;
