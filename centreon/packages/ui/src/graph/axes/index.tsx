import { Axis } from '@visx/visx';
import { isNil } from 'ramda';

import { formatMetricValue, getUnits } from '../../timeSeries';
import useLocaleDateTimeFormat from '../../utils/useLocaleDateTimeFormat';
import { commonTickLabelProps } from '../common';

import UnitLabel from './UnitLabel';

interface AxisYRight {
  [x: string]: unknown;
  displayAxisYRight?: boolean;
  displayUnitAxisYRight?: boolean;
}

interface AxisYLeft {
  [x: string]: unknown;
  displayUnitAxisYLeft?: boolean;
}
interface Data {
  axisX?: Record<string, unknown>;
  axisYLeft?: AxisYLeft;
  axisYRight?: AxisYRight;
  graphData: any;
  lines: any;
  timeSeries: any;
}

interface Axes {
  data: Data;
  height: number;
  leftScale: any;
  rightScale: any;
  width: number;
  xScale: any;
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
  const { lines, graphData } = data;

  const [firstUnit, secondUnit, thirdUnit] = getUnits(lines);

  const hasMoreThanTwoUnits = !isNil(thirdUnit);
  const hasTwoUnits = !isNil(secondUnit) && !hasMoreThanTwoUnits;

  const { base } = graphData.global;

  const xAxisTickFormat = 'LT';
  const xTickCount = Math.ceil(width / 82);
  const ticksCount = Math.ceil(height / 30);

  const displayAxisRight = data?.axisYRight?.displayAxisYRight ?? hasTwoUnits;
  const displayUnitAxisLeft =
    data?.axisYLeft?.displayUnitAxisYLeft ?? !hasMoreThanTwoUnits;
  const displayUnitAxisRight =
    data?.axisYLeft?.displayUnitAxisYLeft ?? hasTwoUnits;

  const formatAxisTick = (tick): string =>
    format({ date: new Date(tick), formatString: xAxisTickFormat });

  const formatTick =
    ({ unit }) =>
    (value): string => {
      if (isNil(value)) {
        return '';
      }

      return formatMetricValue({ base, unit, value }) as string;
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
