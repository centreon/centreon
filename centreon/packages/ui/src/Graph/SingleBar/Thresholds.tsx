import { Thresholds } from '../common/models';

import { ThresholdLine } from './ThresholdLine';

export const groupMargin = 25;

interface Props {
  hideTooltip: () => void;
  showTooltip: (args) => void;
  thresholds: Thresholds;
  xScale: (value: number) => number;
}

const Thresholds = ({
  xScale,
  thresholds,
  showTooltip,
  hideTooltip
}: Props): JSX.Element => (
  <>
    {thresholds.warning.map(({ value, label }) => (
      <ThresholdLine
        hideTooltip={hideTooltip}
        key={`warning-${value.toString()}`}
        label={label}
        showTooltip={showTooltip}
        thresholdType="warning"
        value={value}
        xScale={xScale}
      />
    ))}
    {thresholds.critical.map(({ value, label }) => (
      <ThresholdLine
        hideTooltip={hideTooltip}
        key={`critical-${value.toString()}`}
        label={label}
        showTooltip={showTooltip}
        thresholdType="critical"
        value={value}
        xScale={xScale}
      />
    ))}
  </>
);

export default Thresholds;
