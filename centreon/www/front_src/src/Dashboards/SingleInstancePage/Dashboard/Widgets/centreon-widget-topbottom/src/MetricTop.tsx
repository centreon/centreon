import { Box } from '@mui/material';

import { LineChartData, SingleBar } from '@centreon/ui';

import { Link } from 'react-router';

import { FormThreshold } from '../../models';
import useThresholds from '../../useThresholds';
import { getResourcesUrlForMetricsWidgets } from '../../utils';
import { Resource } from './models';
import { useTopBottomStyles } from './TopBottom.styles';

interface MetricTopProps {
  displayAsRaw: boolean;
  isFromPreview?: boolean;
  metricTop: Resource;
  showLabels: boolean;
  thresholds: FormThreshold;
  unit: string;
}

const MetricTop = ({
  metricTop,
  unit,
  thresholds,
  displayAsRaw,
  showLabels,
  isFromPreview
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

  const formattedThresholds = useThresholds({
    data: formattedData,
    displayAsRaw,
    metricName: `${metricTop.parentName}_${metricTop.name}`,
    thresholds
  });

  return (
    <Box className={classes.singleBarContainer}>
      <Link
        className={classes.linkToResourcesStatus}
        data-testid={`link to ${metricTop?.name}`}
        onClick={(e) => isFromPreview && e.preventDefault()}
        target="_blank"
        to={getResourcesUrlForMetricsWidgets(metricTop)}
      >
        <SingleBar
          data={formattedData}
          displayAsRaw={displayAsRaw}
          showLabels={showLabels}
          size="small"
          thresholds={formattedThresholds}
        />
      </Link>
    </Box>
  );
};

export default MetricTop;
