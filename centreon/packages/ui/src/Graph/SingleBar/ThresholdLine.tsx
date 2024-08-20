import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { margins } from '../common/margins';

import { groupMargin } from './Thresholds';

export const barHeights = {
  medium: 72,
  small: 22
};
export const margin = 40;

const lineMargins = {
  medium: 10,
  small: 5
};

interface Props {
  barHeight: number;
  hideTooltip: () => void;
  isSmall: boolean;
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
  size,
  barHeight,
  isSmall
}: Props): JSX.Element => {
  const theme = useTheme();

  const scaledValue = xScale(value) || 0;

  const lineMargin = lineMargins[size];

  const onMouseEnter = (): void =>
    showTooltip({
      tooltipData: label
    });

  const lineColor = equals(thresholdType, 'warning')
    ? theme.palette.warning.main
    : theme.palette.error.main;

  return (
    <>
      <line
        data-testid={`${thresholdType}-line-${value}`}
        stroke={lineColor}
        strokeDasharray="6, 6"
        strokeWidth={2}
        x1={scaledValue}
        x2={scaledValue}
        y1={
          isSmall
            ? groupMargin - lineMargin + 6
            : groupMargin + lineMargin + margins.top
        }
        y2={
          isSmall
            ? barHeight + groupMargin - lineMargin + margins.top - 2
            : barHeight + groupMargin + lineMargin + 2 * margins.top
        }
      />
      <line
        data-testid={`${thresholdType}-line-${value}-tooltip`}
        stroke="transparent"
        strokeWidth={5}
        x1={scaledValue}
        x2={scaledValue}
        y1={
          isSmall
            ? groupMargin - lineMargin + 5
            : groupMargin + lineMargin + margins.top
        }
        y2={
          isSmall
            ? barHeight + groupMargin - lineMargin + margins.top + 5
            : barHeight + groupMargin + lineMargin + 2 * margins.top
        }
        onMouseEnter={onMouseEnter}
        onMouseLeave={hideTooltip}
      />
    </>
  );
};
