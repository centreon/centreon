import { useMemo } from 'react';

import { Group, Tooltip } from '@visx/visx';
import { scaleLinear } from '@visx/scale';
import { head } from 'ramda';
import { Bar } from '@visx/shape';
import { useSpring, animated } from '@react-spring/web';

import { alpha, Fade, useTheme } from '@mui/material';

import { getMetricWithLatestData } from '../common/timeSeries';
import { Metric } from '../common/timeSeries/models';
import { getColorFromDataAndTresholds } from '../common/utils';

import { SingleBarProps } from './models';
import Thresholds, { barHeight, margin } from './Thresholds';

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
  thresholdTooltipLabels,
  thresholds,
  width,
  height
}: Props): JSX.Element => {
  const theme = useTheme();

  const metric = getMetricWithLatestData(data) as Metric;
  const latestMetricData = head(metric.data) as number;
  const adaptedMaxValue = Math.max(
    metric.maximum_value || 0,
    Math.max(...thresholds) * 1.1,
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

  const xScale = useMemo(
    () =>
      scaleLinear<number>({
        domain: [0, adaptedMaxValue],
        range: [0, width],
        round: true
      }),
    [width]
  );

  const barColor = useMemo(
    () =>
      getColorFromDataAndTresholds({
        data: latestMetricData,
        theme,
        thresholds
      }),
    [latestMetricData, thresholds, theme]
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
    <>
      <svg height={height} width={width}>
        <Group.Group>
          <animated.rect
            fill={barColor}
            height={60}
            rx={4}
            style={springStyle}
            x={0}
            y={margin}
          />
          <Bar
            fill="transparent"
            height={barHeight}
            rx={4}
            ry={4}
            stroke={alpha(theme.palette.text.primary, 0.3)}
            width={maxBarWidth}
            x={0}
            y={margin}
          />
          <Thresholds
            hideTooltip={hideTooltip}
            showTooltip={showTooltip}
            thresholdTooltipLabels={thresholdTooltipLabels}
            thresholds={thresholds}
            xScale={xScale}
          />
        </Group.Group>
      </svg>
      <Fade in={tooltipOpen}>
        <Tooltip.Tooltip
          left={tooltipLeft}
          style={{
            ...baseStyles,
            backgroundColor: theme.palette.background.paper,
            color: theme.palette.text.primary
          }}
          top={tooltipTop}
        >
          {tooltipData}
        </Tooltip.Tooltip>
      </Fade>
    </>
  );
};

export default ResponsiveSingleBar;
