import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { margin } from '../common';

interface Props {
  hideTooltip: () => void;
  label: string;
  showTooltip: (args) => void;
  thresholdType: string;
  value: number;
  width: number;
  yScale: (value: number) => number;
}

export const ThresholdLine = ({
  value,
  label,
  yScale,
  thresholdType,
  showTooltip,
  hideTooltip,
  width
}: Props): JSX.Element => {
  const theme = useTheme();

  const scaledValue = yScale(value);

  const onMouseEnter = (): void =>
    showTooltip({
      tooltipData: label,
      tooltipLeft: -(margin.left + margin.right),
      tooltipTop: scaledValue
    });

  const lineColor = equals(thresholdType, 'warning')
    ? theme.palette.warning.main
    : theme.palette.error.main;

  return (
    <>
      <line
        data-testid={`${thresholdType}-line-${value}`}
        stroke={lineColor}
        strokeDasharray="5,5"
        strokeWidth={2}
        x1={0}
        x2={width}
        y1={scaledValue}
        y2={scaledValue}
      />
      <line
        data-testid={`${thresholdType}-line-${value}-tooltip`}
        stroke="transparent"
        strokeWidth={5}
        x1={0}
        x2={width}
        y1={scaledValue}
        y2={scaledValue}
        onMouseEnter={onMouseEnter}
        onMouseLeave={hideTooltip}
      />
    </>
  );
};
