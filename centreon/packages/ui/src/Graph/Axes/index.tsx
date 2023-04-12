import { Axis } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';

import { formatMetricValue, getUnits } from '../../timeSeries';
import { Line, TimeValue } from '../../timeSeries/models';
import useLocaleDateTimeFormat from '../../utils/useLocaleDateTimeFormat';
import { commonTickLabelProps } from '../common';

import UnitLabel from './UnitLabel';

interface Axis {
  [x: string]: unknown;
  displayUnits?: boolean;
}

interface AxisYRight extends Axis {
  displayAxisYRight?: boolean;
}
interface Data {
  axisX?: Record<string, unknown>;
  axisYLeft?: Axis;
  axisYRight?: AxisYRight;
  baseAxis: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

interface Axes {
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
}: Axes): JSX.Element => {
  const { format } = useLocaleDateTimeFormat();
  const { lines } = data;

  const [firstUnit, secondUnit, thirdUnit] = getUnits(lines);

  const hasMoreThanTwoUnits = !isNil(thirdUnit);
  const hasTwoUnits = !isNil(secondUnit) && !hasMoreThanTwoUnits;

  const xAxisTickFormat = 'LT';
  const xTickCount = Math.ceil(width / 82);
  const ticksCount = Math.ceil(height / 30);

  const displayAxisRight = data?.axisYRight?.displayAxisYRight ?? hasTwoUnits;
  const displayUnitAxisLeft =
    data?.axisYLeft?.displayUnits ?? !hasMoreThanTwoUnits;
  const displayUnitAxisRight = data?.axisYLeft?.displayUnits ?? hasTwoUnits;

  const formatAxisTick = (tick): string =>
    format({ date: new Date(tick), formatString: xAxisTickFormat });

  const formatTick =
    ({ unit }) =>
    (value): string => {
      if (isNil(value)) {
        return '';
      }

      return formatMetricValue({ base: data.baseAxis, unit, value }) as string;
    };

  const formatAxisYLeftTick = formatTick({
    unit: hasMoreThanTwoUnits ? '' : firstUnit
  });

  return (
    <>
      <Axis.AxisBottom
        numTicks={xTickCount}
        scale={xScale}
        tickFormat={formatAxisTick}
        tickLabelProps={(): Record<string, unknown> => ({
          ...commonTickLabelProps,
          textAnchor: 'middle'
        })}
        top={height}
        {...data?.axisX}
      />

      {displayUnitAxisLeft && <UnitLabel unit={firstUnit} x={0} />}

      <Axis.AxisLeft
        numTicks={ticksCount}
        orientation="right"
        scale={leftScale}
        tickFormat={formatAxisYLeftTick}
        tickLabelProps={(): Record<string, unknown> => ({
          ...commonTickLabelProps,
          dx: -2,
          dy: 4,
          textAnchor: 'end'
        })}
        tickLength={2}
        {...data?.axisYLeft}
      />

      {displayAxisRight && (
        <Axis.AxisRight
          left={width}
          numTicks={ticksCount}
          orientation="right"
          scale={rightScale}
          tickFormat={formatTick({ unit: secondUnit })}
          tickLabelProps={(): Record<string, unknown> => ({
            ...commonTickLabelProps,
            dx: 4,
            dy: 4,
            textAnchor: 'start'
          })}
          tickLength={2}
          {...data?.axisYRight}
        />
      )}
      {displayUnitAxisRight && <UnitLabel unit={secondUnit} x={width} />}
    </>
  );
};

export default Axes;
