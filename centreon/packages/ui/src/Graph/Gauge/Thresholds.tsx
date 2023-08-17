import { scaleOrdinal } from '@visx/scale';
import { Pie } from '@visx/shape';
import { identity } from 'ramda';

import { useTheme } from '@mui/material';

import AnimatedPie from './AnimatedPie';
import { GaugeProps } from './models';

export const thresholdThickness = 12;

const Thresholds = ({
  thresholds,
  radius,
  adaptedMaxValue,
  showTooltip,
  hideTooltip,
  metric,
  thresholdTooltipLabels
}: Omit<GaugeProps, 'width' | 'height'>): JSX.Element => {
  const theme = useTheme();

  const adaptedThresholds = [...thresholds, adaptedMaxValue].map(
    (threshold, index) => {
      return threshold - (thresholds[index - 1] || 0);
    }
  );

  const getThresholdColor = scaleOrdinal({
    domain: adaptedThresholds,
    range: [
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
      pieValue={identity}
      startAngle={Math.PI / 2}
    >
      {(pie) => (
        <AnimatedPie<number>
          {...pie}
          animate
          getColor={(arc) => getThresholdColor(arc.data)}
          getKey={(arc) => `${arc.data}`}
          hideTooltip={hideTooltip}
          metric={metric}
          showTooltip={showTooltip}
          thresholdTooltipLabels={thresholdTooltipLabels}
          thresholds={adaptedThresholds}
        />
      )}
    </Pie>
  );
};

export default Thresholds;
