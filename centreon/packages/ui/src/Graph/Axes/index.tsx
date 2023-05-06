import { Axis } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';

import useLocaleDateTimeFormat from '../../utils/useLocaleDateTimeFormat';
import { getUnits } from '../timeSeries';
import { getXAxisTickFormat } from '../helpers';
import { GraphParameters } from '../models';

import UnitLabel from './UnitLabel';
import { Data } from './models';
import useAxisY from './useAxisY';

interface Props {
  data: Data;
  graphInterval: GraphParameters;
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
  xScale,
  graphInterval
}: Props): JSX.Element => {
  const { format } = useLocaleDateTimeFormat();
  const { lines } = data;

  const { axisLeft, axisRight } = useAxisY({ data, graphHeight: height });

  const [firstUnit, secondUnit] = getUnits(lines);

  const xTickCount = Math.ceil(width / 82);

  const tickFormat =
    data?.axisX?.xAxisTickFormat ?? getXAxisTickFormat(graphInterval);

  const formatAxisTick = (tick): string =>
    format({ date: new Date(tick), formatString: tickFormat });

  return (
    <g>
      <Axis.AxisBottom
        numTicks={xTickCount}
        scale={xScale}
        tickFormat={formatAxisTick}
        top={height}
      />

      {axisLeft.displayUnit && <UnitLabel unit={firstUnit} x={-4} />}

      <Axis.AxisLeft
        numTicks={axisLeft.numTicks}
        orientation="left"
        scale={leftScale}
        tickFormat={axisLeft.tickFormat}
        tickLabelProps={axisLeft.tickLabelProps}
        tickLength={2}
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
        />
      )}
      {axisRight.displayUnit && <UnitLabel unit={secondUnit} x={width} />}
    </g>
  );
};

export default Axes;
