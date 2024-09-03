import { inc } from 'ramda';
import { Link } from 'react-router-dom';

import { Box, Typography } from '@mui/material';

import { LineChartData, SingleBar } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { FormThreshold } from '../../models';
import useThresholds from '../../useThresholds';
import { getResourcesUrlForMetricsWidgets } from '../../utils';

import { useTopBottomStyles } from './TopBottom.styles';
import { Resource } from './models';

interface MetricTopProps {
  displayAsRaw: boolean;
  index: number;
  isFromPreview?: boolean;
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
    <>
      <Typography className={classes.resourceLabel}>
        <Tooltip
          followCursor={false}
          label={`${metricTop.parentName}_${metricTop.name}`}
          placement="top"
        >
          <Link
            className={classes.linkToResourcesStatus}
            data-testid={`link to ${metricTop?.name}`}
            target="_blank"
            to={getResourcesUrlForMetricsWidgets(metricTop)}
          >
            <strong>
              #{inc(index)} {`${metricTop.parentName}_${metricTop.name}`}
            </strong>
          </Link>
        </Tooltip>
      </Typography>
      <Box className={classes.singleBarContainer} style={{ height: 50 }}>
        <Link
          className={classes.linkToResourcesStatus}
          data-testid={`link to ${metricTop?.name}`}
          target="_blank"
          to={getResourcesUrlForMetricsWidgets(metricTop)}
          onClick={(e) => isFromPreview && e.preventDefault()}
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
    </>
  );
};

export default MetricTop;
