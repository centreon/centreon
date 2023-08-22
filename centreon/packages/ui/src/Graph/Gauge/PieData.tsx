import { scaleOrdinal } from '@visx/scale';
import { Pie } from '@visx/shape';
import { T, always, cond, gt, head, identity } from 'ramda';

import { Theme, useTheme } from '@mui/material';

import { thresholdThickness } from './Thresholds';
import AnimatedPie from './AnimatedPie';
import { GaugeProps } from './models';

const dataThickness = 45;

interface GetColorFromDataProps {
  data: number;
  theme: Theme;
  thresholds: Array<number>;
}

const getColorFromData = ({
  data,
  thresholds,
  theme
}: GetColorFromDataProps): string =>
  cond([
    [gt(head(thresholds) as number), always(theme.palette.success.main)],
    [gt(thresholds[1]), always(theme.palette.warning.main)],
    [T, always(theme.palette.error.main)]
  ])(data);

const PieData = ({
  metric,
  adaptedMaxValue,
  thresholds,
  radius
}: Omit<
  GaugeProps,
  'width' | 'height' | 'showTooltip' | 'hideTooltip' | 'thresholdTooltipLabels'
>): JSX.Element => {
  const theme = useTheme();

  const pieData = [metric.data[0], adaptedMaxValue - metric.data[0]];
  const pieColor = getColorFromData({
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
