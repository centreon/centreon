import { useRef } from 'react';

import { Group } from '@visx/group';
import { head } from 'ramda';
import { Tooltip } from '@visx/visx';

import { Box, Fade, useTheme } from '@mui/material';

import { Metric } from '../common/timeSeries/models';
import { formatMetricValue } from '../common/timeSeries';
import { getColorFromDataAndTresholds } from '../common/utils';

import Thresholds from './Thresholds';
import PieData from './PieData';

const margin = { bottom: 20, left: 20, right: 20, top: 20 };

interface Props {
  height: number;
  metric: Metric;
  thresholdTooltipLabels: Array<string>;
  thresholds: Array<number>;
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
  metric,
  thresholdTooltipLabels
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

  const innerWidth = width - margin.left - margin.right;
  const innerHeight = height - margin.top - margin.bottom;
  const centerY = innerHeight / 2;
  const centerX = innerWidth / 2;
  const radius = Math.min(innerWidth, innerHeight) / 2;
  const adaptedMaxValue = Math.max(
    metric.maximum_value || 0,
    Math.max(...thresholds) * 1.1,
    head(metric.data) as number
  );

  const pieColor = getColorFromDataAndTresholds({
    data: metric.data[0],
    theme,
    thresholds
  });

  const svgTop = svgRef.current?.getBoundingClientRect().top || 0;
  const svgLeft = svgRef.current?.getBoundingClientRect().left || 0;

  return (
    <Box sx={{ position: 'relative' }}>
      <svg height={height} ref={svgRef} width={width}>
        <Group left={centerX + margin.left} top={centerY + margin.top}>
          <Thresholds
            adaptedMaxValue={adaptedMaxValue}
            hideTooltip={hideTooltip}
            metric={metric}
            radius={radius}
            showTooltip={showTooltip}
            thresholdTooltipLabels={thresholdTooltipLabels}
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
          y={100 + Math.min(width, height) / 6}
        >
          {formatMetricValue({
            base: 1000,
            unit: metric.unit,
            value: metric.data[0]
          })}{' '}
          {metric.unit}
        </text>
      </svg>
      <Fade in={tooltipOpen}>
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
