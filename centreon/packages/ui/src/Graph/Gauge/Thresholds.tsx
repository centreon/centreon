import { scaleOrdinal } from '@visx/scale';
import { Pie } from '@visx/shape';
import { equals, pluck } from 'ramda';

import { useTheme } from '@mui/material';

import AnimatedPie from './AnimatedPie';
import { GaugeProps } from './models';
import { angles } from './utils';

export const thresholdThickness = 12;

const Thresholds = ({
  thresholds,
  radius,
  adaptedMaxValue,
  showTooltip,
  hideTooltip,
  metric
}: Omit<GaugeProps, 'width' | 'height'>): JSX.Element | null => {
  const theme = useTheme();

  const criticalThresholdValues = pluck('value', thresholds.critical);
  const warningThresholdValues = pluck('value', thresholds.warning);

  const isCriticalLowerThanWarning =
    criticalThresholdValues[0] < warningThresholdValues[0];

  const criticalThreshold = isCriticalLowerThanWarning
    ? [0, ...criticalThresholdValues, adaptedMaxValue]
    : [...criticalThresholdValues, adaptedMaxValue];
  const criticalThresholdArc = criticalThreshold.map((value, index) => {
    return {
      name: equals(1, index) ? 'critical' : `transparent-${index}-critical`,
      value: value - (criticalThreshold[index - 1] || 0)
    };
  });
  const criticalThresholdScaleOrdinal = scaleOrdinal({
    domain: pluck('name', criticalThresholdArc),
    range: ['transparent', theme.palette.error.main, 'transparent']
  });

  const warningThreshold = isCriticalLowerThanWarning
    ? [0, ...warningThresholdValues, adaptedMaxValue]
    : [...warningThresholdValues, adaptedMaxValue];
  const warningThresholdArc = warningThreshold.map((value, index) => {
    return {
      name: equals(1, index) ? 'warning' : `transparent-${index}-warning`,
      value: value - (warningThreshold[index - 1] || 0)
    };
  });
  const warningThresholdScaleOrdinal = scaleOrdinal({
    domain: pluck('name', warningThresholdArc),
    range: ['transparent', theme.palette.warning.main, 'transparent']
  });

  const successThresholdArc = [adaptedMaxValue].map((value) => {
    return {
      name: 'success',
      value
    };
  });
  const successThresholdScaleOrdinal = scaleOrdinal({
    domain: pluck('name', successThresholdArc),
    range: [theme.palette.success.main]
  });

  if (!thresholds.enabled) {
    return null;
  }

  const arcs = [
    {
      thresholdArc: successThresholdArc,
      thresholdScaleOrdinal: successThresholdScaleOrdinal
    },
    {
      thresholdArc: warningThresholdArc,
      thresholdScaleOrdinal: warningThresholdScaleOrdinal
    },
    {
      thresholdArc: criticalThresholdArc,
      thresholdScaleOrdinal: criticalThresholdScaleOrdinal
    }
  ];

  const thresholdThicknessFactor = radius / thresholdThickness / 15;

  return (
    <>
      {arcs.map(({ thresholdArc, thresholdScaleOrdinal }) => (
        <Pie
          {...angles}
          data={thresholdArc}
          innerRadius={radius - thresholdThickness * thresholdThicknessFactor}
          key={`arc-${thresholdArc[0].name}`}
          outerRadius={radius}
          pieSortValues={() => -1}
          pieValue={(d) => d.value}
        >
          {(pie) => (
            <AnimatedPie<{ name: string; value: number }>
              {...pie}
              animate
              getColor={(arc) => thresholdScaleOrdinal(arc.data.name)}
              getKey={(arc) => `${arc.data.name}_${arc.data.value}`}
              hideTooltip={hideTooltip}
              metric={metric}
              showTooltip={showTooltip}
              thresholds={thresholds}
            />
          )}
        </Pie>
      ))}
    </>
  );
};

export default Thresholds;
