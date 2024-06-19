import { Axis } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';

import { GraphInterval, useLocaleDateTimeFormat } from '@centreon/ui';

import { getXAxisTickFormat } from '../../LineChart/helpers';
import { getUnits } from '../timeSeries';

import UnitLabel from './UnitLabel';
import { Data } from './models';
import useAxisY from './useAxisY';

interface Props {
  data: Data;
  graphInterval: GraphInterval;
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
  const { lines, showBorder, yAxisTickLabelRotation } = data;

  const { axisLeft, axisRight } = useAxisY({ data, graphHeight: height });

  const [firstUnit, secondUnit, thirdUnit] = getUnits(lines);

  const xTickCount = Math.min(Math.ceil(width / 82), 12);

  const tickFormat =
    data?.axisX?.xAxisTickFormat ?? getXAxisTickFormat(graphInterval);

  const formatAxisTick = (tick): string =>
    format({ date: new Date(tick), formatString: tickFormat });

  const hasMoreThanTwoUnits = !isNil(thirdUnit);
  const displayAxisRight = !isNil(secondUnit) && !hasMoreThanTwoUnits;

  return (
    <g>
      <Axis.AxisBottom
        numTicks={xTickCount}
        scale={xScale}
        strokeWidth={!isNil(showBorder) && !showBorder ? 0 : 1}
        tickFormat={formatAxisTick}
        top={height - 5}
      />

      {axisLeft.displayUnit && <UnitLabel unit={firstUnit} x={-4} />}

      <Axis.AxisLeft
        numTicks={axisLeft?.numTicks}
        orientation="left"
        scale={leftScale}
        strokeWidth={!isNil(showBorder) && !showBorder ? 0 : 1}
        tickFormat={axisLeft.tickFormat}
        tickLabelProps={() => ({
          ...axisLeft.tickLabelProps(),
          angle: yAxisTickLabelRotation
        })}
        tickLength={2}
      />

      {displayAxisRight && (
        <Axis.AxisRight
          left={width}
          numTicks={axisRight?.numTicks}
          orientation="right"
          scale={rightScale}
          strokeWidth={!isNil(showBorder) && !showBorder ? 0 : 1}
          tickFormat={axisRight.tickFormat}
          tickLabelProps={() => ({
            ...axisRight.tickLabelProps(),
            angle: yAxisTickLabelRotation
          })}
          tickLength={2}
        />
      )}
      {axisRight.displayUnit && <UnitLabel unit={secondUnit} x={width} />}
    </g>
  );
};

export default Axes;
