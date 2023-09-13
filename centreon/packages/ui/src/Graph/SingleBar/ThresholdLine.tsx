import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { groupMargin } from './Thresholds';

export const barHeights = {
  medium: 72,
  small: 22
};
export const margin = 40;

const lineMargin = 10;

interface Props {
  hideTooltip: () => void;
  label: string;
  showTooltip: (args) => void;
  size: 'small' | 'medium';
  thresholdType: string;
  value: number;
  xScale: (value: number) => number;
}

export const ThresholdLine = ({
  value,
  label,
  xScale,
  thresholdType,
  showTooltip,
  hideTooltip,
  size
}: Props): JSX.Element => {
  const theme = useTheme();

  const scaledValue = xScale(value) || 0;

  const thresholdLineHeight = barHeights[size] + 2 * lineMargin;

  const bottom = barHeights[size] + margin * 2;

  const onMouseEnter = (left) => (): void =>
    showTooltip({
      tooltipData: label,
      tooltipLeft: left,
      tooltipTop: bottom
    });

  const lineColor = equals(thresholdType, 'warning')
    ? theme.palette.warning.main
    : theme.palette.error.main;

  return (
    <>
      <line
        data-testid={`${thresholdType}-line-${value}`}
        stroke={lineColor}
        strokeDasharray={4}
        strokeWidth={2}
        x1={scaledValue}
        x2={scaledValue}
        y1={groupMargin + 3 * lineMargin}
        y2={thresholdLineHeight + groupMargin + 3 * lineMargin}
      />
      <line
        data-testid={`${thresholdType}-line-${value}-tooltip`}
        stroke="transparent"
        strokeWidth={5}
        x1={scaledValue}
        x2={scaledValue}
        y1={groupMargin + 3 * lineMargin}
        y2={thresholdLineHeight + groupMargin + 3 * lineMargin}
        onMouseEnter={onMouseEnter(scaledValue)}
        onMouseLeave={hideTooltip}
      />
    </>
  );
};
