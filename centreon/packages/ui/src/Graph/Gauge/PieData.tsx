import { scaleOrdinal } from '@visx/scale';
import { Pie } from '@visx/shape';
import { identity } from 'ramda';

import { useTheme } from '@mui/material';

import { getColorFromDataAndTresholds } from '../common/utils';

import { thresholdThickness } from './Thresholds';
import AnimatedPie from './AnimatedPie';
import { GaugeProps } from './models';
import { angles } from './utils';

const dataThickness = 45;

const PieData = ({
  metric,
  adaptedMaxValue,
  thresholds,
  radius,
  baseColor
}: Omit<
  GaugeProps,
  'width' | 'height' | 'showTooltip' | 'hideTooltip' | 'thresholdTooltipLabels'
>): JSX.Element => {
  const theme = useTheme();

  const pieData = [metric.data[0], adaptedMaxValue - metric.data[0]];
  const pieColor = getColorFromDataAndTresholds({
    baseColor,
    data: metric.data[0],
    theme,
    thresholds
  });

  const getDataColor = scaleOrdinal({
    domain: pieData,
    range: [pieColor, 'transparent']
  });

  const dataThicknessFactor = radius / dataThickness / 3;
  const thresholdThicknessFactor = radius / thresholdThickness / 15;

  return (
    <Pie
      {...angles}
      data={pieData}
      innerRadius={radius - dataThickness * dataThicknessFactor}
      outerRadius={
        radius - thresholdThickness * thresholdThicknessFactor * 1.25
      }
      pieSortValues={() => -1}
      pieValue={identity}
    >
      {(pie) => (
        <AnimatedPie<number>
          {...pie}
          animate
          getColor={(arc) => getDataColor(arc.data)}
          getKey={(arc) => `${arc.data}`}
          thresholds={thresholds}
        />
      )}
    </Pie>
  );
};

export default PieData;
