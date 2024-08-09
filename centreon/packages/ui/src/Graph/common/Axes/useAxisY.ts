import { useMemo } from 'react';

import { isNil } from 'ramda';
import { Axis } from '@visx/visx';

import { useTheme } from '@mui/material';

import { formatMetricValue, getUnits } from '../timeSeries';
import { commonTickLabelProps } from '../utils';

import { Data, LabelProps } from './models';

interface AxisYData {
  displayUnit: boolean;
  numTicks?: number;
  tickFormat: (value: unknown) => string;
  tickLabelProps: Axis.TickLabelProps<unknown>;
  unit: string;
}

interface AxisRightData extends AxisYData {
  display: boolean;
}

interface AxisY {
  axisLeft: AxisYData;
  axisRight: AxisRightData;
}

interface Props {
  data: Omit<Data, 'timeSeries'>;
  graphHeight?: number;
  graphWidth?: number;
  isHorizontal: boolean;
}

const useAxisY = ({
  data,
  graphHeight,
  graphWidth,
  isHorizontal
}: Props): AxisY => {
  const theme = useTheme();

  const { lines } = data;
  const [firstUnit, secondUnit] = getUnits(lines);

  const numTicks = isHorizontal
    ? graphHeight && Math.ceil(graphHeight / 30)
    : graphWidth && Math.ceil(graphWidth / 60);

  const displayAxisRight = data?.axisYRight?.display || Boolean(secondUnit);
  const displayUnitAxisRight =
    data?.axisYRight?.displayUnit || Boolean(secondUnit);
  const displayUnitAxisLeft = data?.axisYLeft?.displayUnit || true;
  const leftUnit = useMemo(
    () => data.axisYLeft?.unit ?? firstUnit,
    [data.axisYLeft?.unit, firstUnit]
  );
  const rightUnit = useMemo(
    () => data.axisYRight?.unit ?? secondUnit,
    [data.axisYRight?.unit, secondUnit]
  );

  const formatTick =
    ({ unit }) =>
    (value): string => {
      if (isNil(value)) {
        return '';
      }

      return formatMetricValue({ base: data.baseAxis, unit, value }) as string;
    };

  const labelProps = ({
    textAnchor,
    ...rest
  }: LabelProps): Record<string, unknown> => ({
    ...commonTickLabelProps,
    textAnchor,
    ...rest
  });

  const tickLabelPropsAxisLeft = (): Record<string, unknown> =>
    labelProps({
      dx: theme.spacing(-1),
      dy: theme.spacing(0.5),
      textAnchor: 'end'
    });

  const tickLabelPropsAxisRight = (): Record<string, unknown> =>
    labelProps({
      dx: theme.spacing(0.5),
      dy: theme.spacing(0.5),
      textAnchor: 'start'
    });

  return {
    axisLeft: {
      displayUnit: displayUnitAxisLeft,
      numTicks,
      tickFormat: formatTick({
        unit: leftUnit
      }),
      tickLabelProps: tickLabelPropsAxisLeft,
      unit: leftUnit
    },
    axisRight: {
      display: displayAxisRight,
      displayUnit: displayUnitAxisRight,
      numTicks,
      tickFormat: formatTick({ unit: rightUnit }),
      tickLabelProps: tickLabelPropsAxisRight,
      unit: rightUnit
    }
  };
};

export default useAxisY;
