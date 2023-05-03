import dayjs from 'dayjs';
import { Axis } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { gte } from 'ramda';

import { getUnits } from '../timeSeries';
import useLocaleDateTimeFormat, {
  dateFormat,
  timeFormat
} from '../../utils/useLocaleDateTimeFormat';
import { GraphEndpoint } from '../models';

import UnitLabel from './UnitLabel';
import { Data } from './models';
import useAxisY from './useAxisY';

interface Props {
  data: Data;
  graphEndpoint?: GraphEndpoint;
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
  graphEndpoint
}: Props): JSX.Element => {
  const { format } = useLocaleDateTimeFormat();
  const { lines } = data;

  const { axisLeft, axisRight } = useAxisY({ data, graphHeight: height });

  const [firstUnit, secondUnit] = getUnits(lines);

  const xTickCount = Math.ceil(width / 82);

  const getXAxisTickFormat = (): string => {
    const parameters = graphEndpoint?.queryParameters;
    if (!parameters) {
      return timeFormat;
    }

    const numberDays = dayjs
      .duration(dayjs(parameters.end).diff(dayjs(parameters.start)))
      .asDays();

    return gte(numberDays, 2) ? dateFormat : timeFormat;
  };

  const tickFormat = data?.axisX?.xAxisTickFormat || getXAxisTickFormat();

  const formatAxisTick = (tick): string =>
    format({ date: new Date(tick), formatString: tickFormat });

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
