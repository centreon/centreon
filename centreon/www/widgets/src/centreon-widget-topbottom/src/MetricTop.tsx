import { inc } from 'ramda';

import { Box, Typography } from '@mui/material';

import { LineChartData, SingleBar } from '@centreon/ui';

import useThresholds from '../../useThresholds';
import { FormThreshold } from '../../models';

import { Resource } from './models';
import { useTopBottomStyles } from './TopBottom.styles';
import useGoToResourceStatus from './useGoToResourceStatus';

interface MetricTopProps {
  displayAsRaw: boolean;
  index: number;
  metricTop: Resource;
  showLabels: boolean;
  thresholds: FormThreshold;
  unit: string;
}

const MetricTop = ({
  metricTop,
  index,
  unit,
  thresholds,
  displayAsRaw,
  showLabels
}: MetricTopProps): JSX.Element => {
  const { classes } = useTopBottomStyles();
  const formattedData: LineChartData = {
    global: {},
    metrics: [
      {
        average_value: null,
        crit: metricTop.criticalHighThreshold,
        critical_high_threshold: metricTop.criticalHighThreshold,
        critical_low_threshold: metricTop.criticalLowThreshold,
        data: [metricTop.currentValue],
        legend: metricTop.name,
        maximum_value: metricTop.max,
        metric: metricTop.name,
        metric_id: metricTop.id,
        minimum_value: metricTop.min,
        unit,
        warning_high_threshold: metricTop.warningHighThreshold,
        warning_low_threshold: metricTop.warningLowThreshold
      }
    ],
    times: []
  };
  const { goToResourceStatus } = useGoToResourceStatus();

  const formattedThresholds = useThresholds({
    data: formattedData,
    displayAsRaw,
    metricName: metricTop.name,
    thresholds
  });

  return (
    <>
      <Typography
        className={classes.resourceLabel}
        onClick={() => goToResourceStatus(metricTop)}
      >
        <strong>
          #{inc(index)} {metricTop.name}
        </strong>
      </Typography>
      <Box style={{ height: 50 }} onClick={() => goToResourceStatus(metricTop)}>
        <SingleBar
          data={formattedData}
          displayAsRaw={displayAsRaw}
          showLabels={showLabels}
          size="small"
          thresholds={formattedThresholds}
        />
      </Box>
    </>
  );
};

export default MetricTop;
