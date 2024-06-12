import { Thresholds as ThresholdsModel } from '../common/models';

import { ThresholdLine } from './ThresholdLine';

export const groupMargin = 25;

interface Props {
  barHeight: number;
  hideTooltip: () => void;
  showTooltip: (args) => void;
  size: 'small' | 'medium';
  thresholds: ThresholdsModel;
  xScale: (value: number) => number;
}

const Thresholds = ({
  xScale,
  thresholds,
  showTooltip,
  hideTooltip,
  size,
  barHeight
}: Props): JSX.Element => (
  <>
    {thresholds.warning.map(({ value, label }) => (
      <ThresholdLine
        barHeight={barHeight}
        hideTooltip={hideTooltip}
        key={`warning-${value.toString()}`}
        label={label}
        showTooltip={showTooltip}
        size={size}
        thresholdType="warning"
        value={value}
        xScale={xScale}
      />
    ))}
    {thresholds.critical.map(({ value, label }) => (
      <ThresholdLine
        barHeight={barHeight}
        hideTooltip={hideTooltip}
        key={`critical-${value.toString()}`}
        label={label}
        showTooltip={showTooltip}
        size={size}
        thresholdType="critical"
        value={value}
        xScale={xScale}
      />
    ))}
  </>
);

export default Thresholds;
