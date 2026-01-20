import { alpha, Box, useTheme } from '@mui/material';

import { animated, useSpring } from '@react-spring/web';
import { scaleLinear } from '@visx/scale';
import { Bar } from '@visx/shape';
import { Group, Tooltip } from '@visx/visx';
import { clamp, equals, flatten, head, pluck } from 'ramda';
import { useMemo, useRef } from 'react';

import { Tooltip as MuiTooltip } from '../../components/Tooltip';
import { margins } from '../common/margins';
import {
  formatMetricValueWithUnit,
  getMetricWithLatestData
} from '../common/timeSeries';
import type { Metric } from '../common/timeSeries/models';
import { useTooltipStyles } from '../common/useTooltipStyles';
import { getColorFromDataAndTresholds } from '../common/utils';
import type { SingleBarProps } from './models';
import { barHeights, lineMargins } from './ThresholdLine';
import Thresholds, { groupMargin } from './Thresholds';

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
  showLabels = true,
  max,
  direction = 'column',
  textWidth
}: Props): JSX.Element => {
  const { classes } = useTooltipStyles();
  const theme = useTheme();

  const metric = getMetricWithLatestData(data) as Metric;
  const latestMetricData = head(metric.data) as number;
  const thresholdValues = thresholds.enabled
    ? flatten([
        pluck('value', thresholds.warning),
        pluck('value', thresholds.critical)
      ])
    : [0];

  const adaptedMaxValue =
    max ||
    Math.max(
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
    [latestMetricData, thresholds, theme, baseColor]
  );

  const isSmall = equals(size, 'small');

  const textStyle = isSmall ? theme.typography.h6 : theme.typography.h4;

  const textHeight = isSmall ? 46 : 27;

  const textY = useMemo(() => {
    if (direction === 'row' && isSmall) {
      return 2;
    }
    if (direction === 'row' && !isSmall) {
      return 22;
    }
    return isSmall ? 10 : 25;
  }, [direction, isSmall]);

  const text = showLabels && (
    <text
      dominantBaseline={direction === 'row' ? 'hanging' : 'middle'}
      style={{
        fill: barColor,
        ...textStyle
      }}
      textAnchor={direction === 'row' ? 'start' : 'middle'}
      x={direction === 'row' ? 0 : '50%'}
      y={textY}
    >
      {formatMetricValueWithUnit({
        base: 1000,
        isRaw: displayAsRaw,
        unit: metric.unit,
        value: metric.data[0]
      })}
    </text>
  );

  const widthMargin = useMemo(
    () => (direction === 'row' && textWidth) || 0,
    [direction, textWidth]
  );

  const xScale = useMemo(
    () =>
      scaleLinear<number>({
        domain: [0, adaptedMaxValue],
        range: [0, width - widthMargin - 10 || 0]
      }),
    [width, adaptedMaxValue, widthMargin]
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

  const barY =
    direction === 'row'
      ? lineMargins[size] / 2
      : groupMargin + (isSmall ? 0 : 2 * margins.top);

  const realBarHeight = !isSmall
    ? clamp(
        barHeights.small,
        barHeights.medium,
        height - textHeight - 2 * margins.top
      )
    : barHeights.small;

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
            <title>single bar</title>
            <Group.Group>
              {text}
              <animated.rect
                data-testid={`${latestMetricData}-bar-${barColor}`}
                fill={barColor}
                height={realBarHeight}
                rx={4}
                style={springStyle}
                x={direction === 'row' ? textWidth : 5}
                y={barY}
              />
              <Bar
                fill="transparent"
                height={realBarHeight}
                rx={4}
                ry={4}
                stroke={alpha(theme.palette.text.primary, 0.3)}
                width={maxBarWidth}
                x={direction === 'row' ? textWidth : 5}
                y={barY}
              />
              {thresholds.enabled && (
                <Thresholds
                  barHeight={realBarHeight}
                  direction={direction}
                  hideTooltip={hideTooltip}
                  isSmall={isSmall}
                  showTooltip={showTooltip}
                  size={size}
                  textWidth={textWidth}
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
