import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { groupMargin } from './Thresholds';

const lineMargin = 10;

const thresholdLineHeight = 60 + 2 * lineMargin;

export const margin = 40;

export const barHeight = 50;

interface Props {
  hideTooltip: () => void;
  label: string;
  showTooltip: (args) => void;
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
  hideTooltip
}: Props): JSX.Element => {
  const theme = useTheme();

  const scaledValue = xScale(value) || 0;

  const bottom = barHeight + margin * 2;

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
