import { Axis } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';

import { getUnits } from '../timeSeries';
import useLocaleDateTimeFormat from '../../utils/useLocaleDateTimeFormat';

import UnitLabel from './UnitLabel';
import { Data } from './models';
import useAxisY from './useAxisY';

interface Props {
  data: Data;
  height: number;
  leftScale: ScaleLinear<number, number>;
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
  xScale
}: Props): JSX.Element => {
  const { format } = useLocaleDateTimeFormat();
  const { lines } = data;

  const { axisLeft, axisRight } = useAxisY({ data, graphHeight: height });

  const [firstUnit, secondUnit] = getUnits(lines);

  const { xAxisTickFormat } = data?.axisX || { xAxisTickFormat: 'LT' };
  const xTickCount = Math.ceil(width / 82);

  const formatAxisTick = (tick): string =>
    format({ date: new Date(tick), formatString: xAxisTickFormat });

  return (
    <g>
      <Axis.AxisBottom
        numTicks={xTickCount}
        scale={xScale}
        tickFormat={formatAxisTick}
        top={height}
        {...data?.axisX}
      />

      {axisLeft.displayUnit && <UnitLabel unit={firstUnit} x={-4} />}

      <Axis.AxisLeft
        numTicks={axisLeft.numTicks}
        orientation="left"
        scale={leftScale}
        tickFormat={axisLeft.tickFormat}
        tickLabelProps={axisLeft.tickLabelProps}
        tickLength={2}
        {...data?.axisYLeft}
      />

      {axisRight.display && (
        <Axis.AxisRight
          left={width}
          numTicks={axisRight.numTicks}
          orientation="right"
          scale={rightScale}
          tickFormat={axisRight.tickFormat}
          tickLabelProps={axisRight.tickLabelProps}
          tickLength={2}
          {...data?.axisYRight}
        />
      )}
      {axisRight.displayUnit && <UnitLabel unit={secondUnit} x={width} />}
    </g>
  );
};

export default Axes;
