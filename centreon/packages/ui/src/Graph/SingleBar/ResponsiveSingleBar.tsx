import { useMemo, useRef } from 'react';

import { animated, useSpring } from '@react-spring/web';
import { scaleLinear } from '@visx/scale';
import { Bar } from '@visx/shape';
import { Group, Tooltip } from '@visx/visx';
import { equals, flatten, head, lt, pluck } from 'ramda';

import { Box, alpha, useTheme } from '@mui/material';

import { Tooltip as MuiTooltip } from '../../components/Tooltip';
import { margins } from '../common/margins';
import {
  formatMetricValueWithUnit,
  getMetricWithLatestData
} from '../common/timeSeries';
import { Metric } from '../common/timeSeries/models';
import { useTooltipStyles } from '../common/useTooltipStyles';
import { getColorFromDataAndTresholds } from '../common/utils';

import { barHeights } from './ThresholdLine';
import Thresholds, { groupMargin } from './Thresholds';
import { SingleBarProps } from './models';

interface Props extends SingleBarProps {
  height: number;
  width: number;
}

const ResponsiveSingleBar = ({
  data,
  thresholds,
  width,
  height,
  displayAsRaw,
  baseColor,
  size = 'medium',
  showLabels = true
}: Props): JSX.Element => {
  const { classes } = useTooltipStyles();
  const theme = useTheme();

  const isSmallHeight = lt(height, 150);

  const metric = getMetricWithLatestData(data) as Metric;
  const latestMetricData = head(metric.data) as number;
  const thresholdValues = thresholds.enabled
    ? flatten([
        pluck('value', thresholds.warning),
        pluck('value', thresholds.critical)
      ])
    : [0];

  const adaptedMaxValue = Math.max(
    metric.maximum_value || 0,
    Math.max(...thresholdValues) * 1.1,
    head(metric.data) as number
  );

  const { showTooltip, hideTooltip, tooltipOpen, tooltipData } =
    Tooltip.useTooltip();
  const svgRef = useRef<SVGSVGElement | null>(null);

  const barColor = useMemo(
    () =>
      getColorFromDataAndTresholds({
        baseColor,
        data: latestMetricData,
        theme,
        thresholds
      }),
    [latestMetricData, thresholds, theme]
  );

  const isSmall = equals(size, 'small') || isSmallHeight;

  const textStyle = isSmall ? theme.typography.h6 : theme.typography.h4;

  const textHeight = isSmall ? 46 : 27;

  const text = showLabels && (
    <text
      dominantBaseline="middle"
      style={{
        fill: barColor,
        ...textStyle
      }}
      textAnchor="middle"
      x="50%"
      y={isSmall ? 10 : 25}
    >
      {formatMetricValueWithUnit({
        base: 1000,
        isRaw: displayAsRaw,
        unit: metric.unit,
        value: metric.data[0]
      })}
    </text>
  );

  const xScale = useMemo(
    () =>
      scaleLinear<number>({
        domain: [0, adaptedMaxValue],
        range: [0, width - 10 || 0]
      }),
    [width, adaptedMaxValue]
  );

  const metricBarWidth = useMemo(
    () => xScale(latestMetricData),
    [xScale, latestMetricData]
  );
  const maxBarWidth = useMemo(
    () => xScale(adaptedMaxValue),
    [xScale, adaptedMaxValue]
  );

  const springStyle = useSpring({ width: metricBarWidth });

  const barHeight = isSmallHeight ? barHeights.small : barHeights[size];

  const barY = groupMargin + (isSmall ? 0 : 2 * margins.top);

  const realBarHeight =
    !isSmall && textHeight + barHeight > height
      ? height - textHeight - 2 * margins.top
      : barHeight;

  return (
    <div
      style={{
        height: '100%',
        position: 'relative'
      }}
    >
      <Box
        sx={{
          height: '100%',
          overflow: 'hidden',
          position: 'relative',
          width: '100%'
        }}
      >
        <MuiTooltip
          classes={{
            tooltip: classes.tooltip
          }}
          label={tooltipData}
          open={tooltipOpen}
          placement="top"
        >
          <svg height={height} ref={svgRef} width={width}>
            <Group.Group>
              {text}
              <animated.rect
                data-testid={`${latestMetricData}-bar-${barColor}`}
                fill={barColor}
                height={realBarHeight}
                rx={4}
                style={springStyle}
                x={5}
                y={barY}
              />
              <Bar
                fill="transparent"
                height={realBarHeight}
                rx={4}
                ry={4}
                stroke={alpha(theme.palette.text.primary, 0.3)}
                width={maxBarWidth}
                x={5}
                y={barY}
              />
              {thresholds.enabled && (
                <Thresholds
                  barHeight={realBarHeight}
                  hideTooltip={hideTooltip}
                  isSmall={isSmall}
                  showTooltip={showTooltip}
                  size={size}
                  thresholds={thresholds}
                  xScale={xScale}
                />
              )}
            </Group.Group>
          </svg>
        </MuiTooltip>
      </Box>
    </div>
  );
};

export default ResponsiveSingleBar;
