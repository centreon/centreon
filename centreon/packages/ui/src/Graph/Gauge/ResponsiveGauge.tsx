import { useRef } from 'react';

import { Group } from '@visx/group';
import { flatten, head, pluck } from 'ramda';
import { Tooltip } from '@visx/visx';

import { Box, Fade, useTheme } from '@mui/material';

import { Metric } from '../common/timeSeries/models';
import { formatMetricValueWithUnit } from '../common/timeSeries';
import { getColorFromDataAndTresholds } from '../common/utils';
import { margins } from '../common/margins';

import Thresholds from './Thresholds';
import PieData from './PieData';
import { GaugeProps } from './models';

interface Props extends Pick<GaugeProps, 'thresholds' | 'baseColor'> {
  displayAsRaw?: boolean;
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
  metric,
  displayAsRaw,
  baseColor
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

  const pieColor = getColorFromDataAndTresholds({
    baseColor,
    data: metric.data[0],
    theme,
    thresholds
  });

  const svgTop = svgRef.current?.getBoundingClientRect().top || 0;
  const svgLeft = svgRef.current?.getBoundingClientRect().left || 0;

  const isSmallWidget = height < 240;

  const gaugeValue = formatMetricValueWithUnit({
    base: 1000,
    isRaw: displayAsRaw,
    unit: metric.unit,
    value: metric.data[0]
  });

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
            baseColor={baseColor}
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
            fontSize:
              Math.min(width, height) / 7 -
              (isSmallWidget ? 0 : (gaugeValue?.length || 0) * 2)
          }}
          textAnchor="middle"
          x="50%"
          y={isSmallWidget ? 130 : height - height / 2.3}
        >
          {gaugeValue}
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
