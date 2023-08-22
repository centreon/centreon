import { scaleOrdinal } from '@visx/scale';
import { Pie } from '@visx/shape';
import { pluck } from 'ramda';

import { useTheme } from '@mui/material';

import AnimatedPie from './AnimatedPie';
import { GaugeProps, ThresholdType } from './models';

export const thresholdThickness = 12;

const Thresholds = ({
  thresholds,
  radius,
  adaptedMaxValue,
  showTooltip,
  hideTooltip,
  metric,
  thresholdTooltipLabels,
  disabledThresholds
}: Omit<GaugeProps, 'width' | 'height'>): JSX.Element => {
  const theme = useTheme();

  const namedThresholds = [
    {
      name: ThresholdType.Success,
      value: thresholds[0]
    },
    {
      name: ThresholdType.Warning,
      value: thresholds[1]
    },
    {
      name: ThresholdType.Error,
      value: adaptedMaxValue
    }
  ];

  const adaptedThresholds = namedThresholds.map(({ name, value }, index) => {
    return {
      name,
      value: value - (namedThresholds[index - 1]?.value || 0)
    };
  });

  const getThresholdColor = scaleOrdinal({
    domain: pluck('name', adaptedThresholds),
    range: disabledThresholds
      ? [
          theme.palette.success.main,
          theme.palette.success.main,
          theme.palette.success.main
        ]
      : [
          theme.palette.success.main,
          theme.palette.warning.main,
          theme.palette.error.main
        ]
  });

  return (
    <Pie
      data={adaptedThresholds}
      endAngle={-Math.PI / 2}
      innerRadius={radius - thresholdThickness}
      outerRadius={radius}
      pieSortValues={() => -1}
      pieValue={(d) => d.value}
      startAngle={Math.PI / 2}
    >
      {(pie) => (
        <AnimatedPie<{ name: ThresholdType; value: number }>
          {...pie}
          animate
          getColor={(arc) => getThresholdColor(arc.data.name)}
          getKey={(arc) => `${arc.data.name}_${arc.data.value}`}
          hideTooltip={hideTooltip}
          metric={metric}
          showTooltip={showTooltip}
          thresholdTooltipLabels={thresholdTooltipLabels}
        />
      )}
    </Pie>
  );
};

export default Thresholds;
