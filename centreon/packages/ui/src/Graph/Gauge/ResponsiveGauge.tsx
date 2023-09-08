import { useRef } from 'react';

import { Group } from '@visx/group';
import { flatten, head, pluck } from 'ramda';
import { Tooltip } from '@visx/visx';

import { Box, Fade, useTheme } from '@mui/material';

import { Metric } from '../common/timeSeries/models';
import { formatMetricValue } from '../common/timeSeries';
import { getColorFromDataAndTresholds } from '../common/utils';
import { margins } from '../common/margins';

import Thresholds from './Thresholds';
import PieData from './PieData';
import { GaugeProps } from './models';

interface Props extends Pick<GaugeProps, 'thresholds'> {
  height: number;
  metric: Metric;
  width: number;
}

const baseStyles = {
  ...Tooltip.defaultStyles,
  textAlign: 'center'
};

const ResponsiveGauge = ({
  width,
  height,
  thresholds,
  metric
}: Props): JSX.Element => {
  const svgRef = useRef<SVGSVGElement>(null);

  const theme = useTheme();

  const {
    showTooltip,
    hideTooltip,
    tooltipOpen,
    tooltipLeft,
    tooltipTop,
    tooltipData
  } = Tooltip.useTooltip();

  const innerWidth = width - margins.left - margins.right;
  const innerHeight = height - margins.top - margins.bottom;
  const centerY = innerHeight / 2;
  const centerX = innerWidth / 2;
  const radius = Math.min(innerWidth, innerHeight) / 2;
  const thresholdValues = flatten([
    pluck('value', thresholds.warning),
    pluck('value', thresholds.critical)
  ]);
  const adaptedMaxValue = Math.max(
    metric.maximum_value || 0,
    Math.max(...thresholdValues) * 1.1,
    head(metric.data) as number
  );

  const pieColor = !thresholds.enabled
    ? theme.palette.success.main
    : getColorFromDataAndTresholds({
        data: metric.data[0],
        theme,
        thresholds
      });

  const svgTop = svgRef.current?.getBoundingClientRect().top || 0;
  const svgLeft = svgRef.current?.getBoundingClientRect().left || 0;

  const isSmallHeight = height < 250;

  return (
    <Box sx={{ position: 'relative' }}>
      <svg height={height} ref={svgRef} width={width}>
        <Group left={centerX + margins.left} top={centerY + height / 6}>
          <Thresholds
            adaptedMaxValue={adaptedMaxValue}
            hideTooltip={hideTooltip}
            metric={metric}
            radius={radius}
            showTooltip={showTooltip}
            thresholds={thresholds}
          />
          <PieData
            adaptedMaxValue={adaptedMaxValue}
            metric={metric}
            radius={radius}
            thresholds={thresholds}
          />
        </Group>
        <text
          dominantBaseline="middle"
          style={{
            fill: pieColor,
            ...theme.typography.h3,
            fontSize: Math.min(width, height) / 7
          }}
          textAnchor="middle"
          x="50%"
          y={isSmallHeight ? 140 : 100 + Math.min(width, height) / 3}
        >
          {formatMetricValue({
            base: 1000,
            unit: metric.unit,
            value: metric.data[0]
          })}{' '}
          {metric.unit}
        </text>
      </svg>
      <Fade in={tooltipOpen && thresholds.enabled}>
        <Tooltip.Tooltip
          left={(tooltipLeft || 0) - svgLeft}
          style={{
            ...baseStyles,
            backgroundColor: theme.palette.background.paper,
            color: theme.palette.text.primary,
            transform: 'translate(-50%, 0)'
          }}
          top={(tooltipTop || 0) - svgTop + 20}
        >
          {tooltipData}
        </Tooltip.Tooltip>
      </Fade>
    </Box>
  );
};

export default ResponsiveGauge;
