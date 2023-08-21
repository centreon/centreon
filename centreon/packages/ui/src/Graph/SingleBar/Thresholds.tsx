import { useTheme } from '@mui/material';

export const margin = 10;

export const barHeight = 60;

interface Props {
  hideTooltip: () => void;
  showTooltip: (args) => void;
  thresholdTooltipLabels: Array<string>;
  thresholds: Array<number>;
  xScale: (value: number) => number;
}

const Thresholds = ({
  xScale,
  thresholds,
  showTooltip,
  hideTooltip,
  thresholdTooltipLabels
}: Props): JSX.Element => {
  const theme = useTheme();

  const warningValue = thresholds[0];
  const criticalValue = thresholds[1];
  const warningTooltipLabel = thresholdTooltipLabels[0];
  const criticalTooltipLabel = thresholdTooltipLabels[1];

  const bottom = barHeight + margin * 2;

  const onMouseEnter =
    ({ label, left }) =>
    (): void =>
      showTooltip({
        tooltipData: label,
        tooltipLeft: left,
        tooltipTop: bottom
      });

  return (
    <>
      <line
        stroke={theme.palette.warning.main}
        strokeDasharray={4}
        strokeWidth={2}
        x1={xScale(warningValue)}
        x2={xScale(warningValue)}
        y1={0}
        y2={bottom}
      />
      <line
        stroke={theme.palette.error.main}
        strokeDasharray={4}
        strokeWidth={2}
        x1={xScale(criticalValue)}
        x2={xScale(criticalValue)}
        y1={0}
        y2={bottom}
      />
      <line
        stroke="transparent"
        strokeWidth={5}
        x1={xScale(warningValue)}
        x2={xScale(warningValue)}
        y1={0}
        y2={bottom}
        onMouseEnter={onMouseEnter({
          label: warningTooltipLabel,
          left: xScale(warningValue)
        })}
        onMouseLeave={hideTooltip}
      />
      <line
        stroke="transparent"
        strokeWidth={5}
        x1={xScale(criticalValue)}
        x2={xScale(criticalValue)}
        y1={0}
        y2={bottom}
        onMouseEnter={onMouseEnter({
          label: criticalTooltipLabel,
          left: xScale(criticalValue)
        })}
        onMouseLeave={hideTooltip}
      />
    </>
  );
};

export default Thresholds;
