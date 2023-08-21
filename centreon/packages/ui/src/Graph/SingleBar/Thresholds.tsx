import { useTheme } from '@mui/material';

export const margin = 40;

export const barHeight = 50;

export const groupMargin = 25;

const lineMargin = 10;

const thresholdLineHeight = 60 + 2 * lineMargin;

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
        y1={groupMargin + 3 * lineMargin}
        y2={thresholdLineHeight + groupMargin + 3 * lineMargin}
      />
      <line
        stroke={theme.palette.error.main}
        strokeDasharray={4}
        strokeWidth={2}
        x1={xScale(criticalValue)}
        x2={xScale(criticalValue)}
        y1={groupMargin + 3 * lineMargin}
        y2={thresholdLineHeight + groupMargin + 3 * lineMargin}
      />
      <line
        stroke="transparent"
        strokeWidth={5}
        x1={xScale(warningValue)}
        x2={xScale(warningValue)}
        y1={groupMargin + 3 * lineMargin}
        y2={thresholdLineHeight + groupMargin + 3 * lineMargin}
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
        y1={groupMargin + 3 * lineMargin}
        y2={thresholdLineHeight + groupMargin + 3 * lineMargin}
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
