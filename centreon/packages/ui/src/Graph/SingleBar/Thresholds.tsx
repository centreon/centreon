import { Thresholds as ThresholdsModel } from '../common/models';
import { SingleBarProps } from './models';

import { ThresholdLine } from './ThresholdLine';

export const groupMargin = 25;

interface Props extends Pick<SingleBarProps, 'direction'> {
  barHeight: number;
  hideTooltip: () => void;
  isSmall: boolean;
  showTooltip: (args) => void;
  size: 'small' | 'medium';
  thresholds: ThresholdsModel;
  xScale: (value: number) => number;
  textWidth?: number;
}

const Thresholds = ({
  xScale,
  thresholds,
  showTooltip,
  hideTooltip,
  size,
  barHeight,
  isSmall,
  direction,
  textWidth
}: Props): JSX.Element => (
  <>
    {thresholds.warning.map(({ value, label }) => (
      <ThresholdLine
        barHeight={barHeight}
        hideTooltip={hideTooltip}
        isSmall={isSmall}
        key={`warning-${value.toString()}`}
        label={label}
        showTooltip={showTooltip}
        size={size}
        thresholdType="warning"
        value={value}
        xScale={xScale}
        direction={direction}
        textWidth={textWidth}
      />
    ))}
    {thresholds.critical.map(({ value, label }) => (
      <ThresholdLine
        barHeight={barHeight}
        hideTooltip={hideTooltip}
        isSmall={isSmall}
        key={`critical-${value.toString()}`}
        label={label}
        showTooltip={showTooltip}
        size={size}
        thresholdType="critical"
        value={value}
        xScale={xScale}
        direction={direction}
        textWidth={textWidth}
      />
    ))}
  </>
);

export default Thresholds;
