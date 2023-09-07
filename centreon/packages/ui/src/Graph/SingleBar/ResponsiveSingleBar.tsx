import { useMemo, useRef } from 'react';

import { Group, Tooltip } from '@visx/visx';
import { scaleLinear } from '@visx/scale';
import { flatten, head, pluck } from 'ramda';
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
import { barHeight, margin } from './ThresholdLine';

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
  displayAsRaw
}: Props): JSX.Element => {
  const theme = useTheme();

  const metric = getMetricWithLatestData(data) as Metric;
  const latestMetricData = head(metric.data) as number;
  const thresholdValues = flatten([
    pluck('value', thresholds.warning),
    pluck('value', thresholds.critical)
  ]);
  const adaptedMaxValue = Math.max(
    metric.maximum_value || 0,
    Math.max(...thresholdValues) * 1.1,
    head(metric.data) as number
  );

  const innerHeight = height;
  const centerY = innerHeight / 4;

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
        data: latestMetricData,
        theme,
        thresholds
      }),
    [latestMetricData, thresholds, theme]
  );

  const text = (
    <text
      dominantBaseline="middle"
      style={{ fill: barColor, ...theme.typography.h3 }}
      textAnchor="middle"
      x="50%"
      y={25}
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
    <Box sx={{ position: 'relative' }}>
      <svg height={height} ref={svgRef} width={width}>
        <Group.Group top={centerY - margins.bottom}>
          {text}
          <animated.rect
            data-testid={`${latestMetricData}-bar-${barColor}`}
            fill={barColor}
            height={barHeight}
            rx={4}
            style={springStyle}
            x={0}
            y={groupMargin + margin}
          />
          <Bar
            fill="transparent"
            height={barHeight}
            rx={4}
            ry={4}
            stroke={alpha(theme.palette.text.primary, 0.3)}
            width={maxBarWidth}
            x={0}
            y={groupMargin + margin}
          />
          {thresholds.enabled && (
            <Thresholds
              hideTooltip={hideTooltip}
              showTooltip={showTooltip}
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
            transform: 'translate(-50%, -20px)'
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
