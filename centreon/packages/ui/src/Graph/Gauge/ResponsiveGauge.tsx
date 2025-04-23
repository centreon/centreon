import { useRef } from 'react';

import { Group } from '@visx/group';
import { Tooltip } from '@visx/visx';
import { flatten, head, pluck } from 'ramda';

import { Box, useTheme } from '@mui/material';

import { Tooltip as MuiTooltip } from '../../components/Tooltip';
import { margins } from '../common/margins';
import { formatMetricValueWithUnit } from '../common/timeSeries';
import { Metric } from '../common/timeSeries/models';
import { useTooltipStyles } from '../common/useTooltipStyles';
import { getColorFromDataAndTresholds } from '../common/utils';

import PieData from './PieData';
import Thresholds from './Thresholds';
import { GaugeProps } from './models';

interface Props extends Pick<GaugeProps, 'thresholds' | 'baseColor'> {
  displayAsRaw?: boolean;
  height: number;
  metric: Metric;
  width: number;
}

const ResponsiveGauge = ({
  width,
  height,
  thresholds,
  metric,
  displayAsRaw,
  baseColor
}: Props): JSX.Element => {
  const { classes } = useTooltipStyles();
  const svgRef = useRef<SVGSVGElement>(null);

  const theme = useTheme();

  const { showTooltip, hideTooltip, tooltipOpen, tooltipData } =
    Tooltip.useTooltip();

  const innerWidth = width - margins.left - margins.right;
  const innerHeight = height - margins.top - margins.bottom;
  const centerY = innerHeight / 2;
  const centerX = innerWidth / 2;
  const baseSize = Math.min(width, height);
  const heightOverWidthRatio = height / width;
  const radius = baseSize / (heightOverWidthRatio > 0.9 ? 2 : 1.8);
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
      <MuiTooltip
        classes={{
          tooltip: classes.tooltip
        }}
        label={tooltipData}
        open={thresholds.enabled && tooltipOpen}
        placement="top"
      >
        <svg height={height} ref={svgRef} width={width}>
          <Group
            left={centerX + margins.left}
            top={centerY + margins.top + baseSize / 8}
          >
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
            <text
              dominantBaseline="middle"
              style={{
                fill: pieColor,
                ...theme.typography.h3,
                fontSize:
                  Math.min(width, height) / (gaugeValue?.length || 1) + 3
              }}
              textAnchor="middle"
              x="0%"
              y="0%"
            >
              {gaugeValue}
            </text>
          </Group>
        </svg>
      </MuiTooltip>
    </Box>
  );
};

export default ResponsiveGauge;
