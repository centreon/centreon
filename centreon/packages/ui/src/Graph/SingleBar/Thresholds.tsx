import type { Thresholds as ThresholdsModel } from '../common/models';
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
        direction={direction}
        hideTooltip={hideTooltip}
        isSmall={isSmall}
        key={`warning-${value.toString()}`}
        label={label}
        showTooltip={showTooltip}
        size={size}
        textWidth={textWidth}
        thresholdType="warning"
        value={value}
        xScale={xScale}
      />
    ))}
    {thresholds.critical.map(({ value, label }) => (
      <ThresholdLine
        barHeight={barHeight}
        direction={direction}
        hideTooltip={hideTooltip}
        isSmall={isSmall}
        key={`critical-${value.toString()}`}
        label={label}
        showTooltip={showTooltip}
        size={size}
        textWidth={textWidth}
        thresholdType="critical"
        value={value}
        xScale={xScale}
      />
    ))}
  </>
);

export default Thresholds;
