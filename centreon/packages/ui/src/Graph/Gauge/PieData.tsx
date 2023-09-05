import { scaleOrdinal } from '@visx/scale';
import { Pie } from '@visx/shape';
import { identity } from 'ramda';

import { useTheme } from '@mui/material';

import { getColorFromDataAndTresholds } from '../common/utils';

import { thresholdThickness } from './Thresholds';
import AnimatedPie from './AnimatedPie';
import { GaugeProps } from './models';

const dataThickness = 45;

const PieData = ({
  metric,
  adaptedMaxValue,
  thresholds,
  radius,
  disabledThresholds
}: Omit<
  GaugeProps,
  'width' | 'height' | 'showTooltip' | 'hideTooltip' | 'thresholdTooltipLabels'
>): JSX.Element => {
  const theme = useTheme();

  const pieData = [metric.data[0], adaptedMaxValue - metric.data[0]];
  const pieColor = disabledThresholds
    ? theme.palette.success.main
    : getColorFromDataAndTresholds({
        data: metric.data[0],
        theme,
        thresholds
      });

  const getDataColor = scaleOrdinal({
    domain: pieData,
    range: [pieColor, 'transparent']
  });

  return (
    <Pie
      data={pieData}
      endAngle={-Math.PI / 2}
      innerRadius={radius - dataThickness}
      outerRadius={radius - thresholdThickness * 1.3}
      pieSortValues={() => -1}
      pieValue={identity}
      startAngle={Math.PI / 2}
    >
      {(pie) => (
        <AnimatedPie<number>
          {...pie}
          animate
          getColor={(arc) => getDataColor(arc.data)}
          getKey={(arc) => `${arc.data}`}
          thresholdTooltipLabels={[]}
        />
      )}
    </Pie>
  );
};

export default PieData;
