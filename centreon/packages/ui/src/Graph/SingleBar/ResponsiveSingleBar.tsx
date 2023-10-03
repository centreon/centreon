import { useMemo, useRef } from 'react';

import { Group, Tooltip } from '@visx/visx';
import { scaleLinear } from '@visx/scale';
import { equals, flatten, head, pluck } from 'ramda';
import { Bar } from '@visx/shape';
import { useSpring, animated } from '@react-spring/web';

import { alpha, Box, Fade, useTheme } from '@mui/material';

import {
  formatMetricValueWithUnit,
  getMetricWithLatestData
} from '../common/timeSeries';
import { Metric } from '../common/timeSeries/models';
import { getColorFromDataAndTresholds } from '../common/utils';
import { margins } from '../common/margins';

import { SingleBarProps } from './models';
import Thresholds, { groupMargin } from './Thresholds';
import { barHeights } from './ThresholdLine';

interface Props extends SingleBarProps {
  height: number;
  width: number;
}

const baseStyles = {
  ...Tooltip.defaultStyles,
  textAlign: 'center'
};

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
  const theme = useTheme();

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

  const {
    showTooltip,
    hideTooltip,
    tooltipOpen,
    tooltipLeft,
    tooltipTop,
    tooltipData
  } = Tooltip.useTooltip();
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

  const isSmall = equals(size, 'small');

  const textStyle = isSmall
    ? {
        ...theme.typography.h6
      }
    : theme.typography.h3;

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
        range: [0, width]
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

  return (
    <Box
      sx={{
        height: '100%',
        overflow: 'hidden',
        position: 'relative',
        width: '100%'
      }}
    >
      <svg height={height} ref={svgRef} width={width}>
        <Group.Group>
          {text}
          <animated.rect
            data-testid={`${latestMetricData}-bar-${barColor}`}
            fill={barColor}
            height={barHeights[size]}
            rx={4}
            style={springStyle}
            x={0}
            y={groupMargin + (isSmall ? 0 : 2 * margins.top)}
          />
          <Bar
            fill="transparent"
            height={barHeights[size]}
            rx={4}
            ry={4}
            stroke={alpha(theme.palette.text.primary, 0.3)}
            width={maxBarWidth}
            x={0}
            y={groupMargin + (isSmall ? 0 : 2 * margins.top)}
          />
          {thresholds.enabled && (
            <Thresholds
              hideTooltip={hideTooltip}
              showTooltip={showTooltip}
              size={size}
              thresholds={thresholds}
              xScale={xScale}
            />
          )}
        </Group.Group>
      </svg>
      <Fade in={tooltipOpen}>
        <Tooltip.Tooltip
          left={tooltipLeft}
          style={{
            ...baseStyles,
            backgroundColor: theme.palette.background.paper,
            color: theme.palette.text.primary,
            transform: `translate(-50%, ${isSmall ? -60 : -20}px)`,
            zIndex: theme.zIndex.tooltip
          }}
          top={tooltipTop}
        >
          {tooltipData}
        </Tooltip.Tooltip>
      </Fade>
    </Box>
  );
};

export default ResponsiveSingleBar;
