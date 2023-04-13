import { Axis } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';

import { useTheme } from '@mui/material';

import { formatMetricValue, getUnits } from '../../timeSeries';
import useLocaleDateTimeFormat from '../../utils/useLocaleDateTimeFormat';
import { commonTickLabelProps } from '../common';

import UnitLabel from './UnitLabel';
import { Data, LabelProps } from './models';

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
  const theme = useTheme();

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

  const labelProps = ({
    textAnchor,
    ...rest
  }: LabelProps): Record<string, unknown> => ({
    ...commonTickLabelProps,
    textAnchor,
    ...rest
  });

  return (
    <>
      <Axis.AxisBottom
        numTicks={xTickCount}
        scale={xScale}
        tickFormat={formatAxisTick}
        tickLabelProps={(): Record<string, unknown> =>
          labelProps({ textAnchor: 'middle' })
        }
        top={height}
        {...data?.axisX}
      />

      {displayUnitAxisLeft && <UnitLabel unit={firstUnit} x={-4} />}

      <Axis.AxisLeft
        numTicks={ticksCount}
        orientation="right"
        scale={leftScale}
        tickFormat={formatAxisYLeftTick}
        tickLabelProps={(): Record<string, unknown> =>
          labelProps({
            dx: theme.spacing(-1),
            dy: theme.spacing(0.5),
            textAnchor: 'end',
            x: 0
          })
        }
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
          tickLabelProps={(): Record<string, unknown> =>
            labelProps({
              dx: theme.spacing(0.5),
              dy: theme.spacing(0.5),
              x: theme.spacing(1)
            })
          }
          tickLength={2}
          {...data?.axisYRight}
        />
      )}
      {displayUnitAxisRight && <UnitLabel unit={secondUnit} x={width} />}
    </>
  );
};

export default Axes;
