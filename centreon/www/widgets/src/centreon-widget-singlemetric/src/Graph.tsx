import { T, always, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import {
  Gauge,
  GraphText,
  SingleBar,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';

import useThresholds from '../../useThresholds';
import { Resource, ServiceMetric, GlobalRefreshInterval } from '../../models';

import { FormThreshold, ValueFormat } from './models';
import {
  labelCritical,
  labelNoDataFound,
  labelWarning
} from './translatedLabels';
import { useNoDataFoundStyles } from './NoDataFound.styles';
import { graphEndpoint } from './api/endpoints';
import { useGraphStyles } from './Graph.styles';

interface Props {
  globalRefreshInterval: GlobalRefreshInterval;
  metrics: Array<ServiceMetric>;
  refreshCount: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<Resource>;
  singleMetricGraphType: 'text' | 'gauge' | 'bar';
  threshold: FormThreshold;
  valueFormat: ValueFormat;
}

const Graph = ({
  metrics,
  singleMetricGraphType,
  threshold,
  refreshInterval,
  refreshIntervalCustom,
  globalRefreshInterval,
  valueFormat,
  refreshCount,
  resources
}: Props): JSX.Element => {
  const { classes } = useNoDataFoundStyles();
  const { classes: graphClasses } = useGraphStyles();

  const { t } = useTranslation();

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const metricId = metrics[0]?.metrics[0]?.id;
  const metricName = metrics[0]?.metrics[0]?.name;

  const { graphData, isGraphLoading, isMetricsEmpty } = useGraphQuery({
    baseEndpoint: graphEndpoint,
    metrics: [metricName],
    refreshCount,
    refreshInterval: refreshIntervalToUse,
    resources
  });

  const displayAsRaw = equals('raw')(valueFormat);

  const formattedThresholds = useThresholds({
    data: graphData,
    displayAsRaw,
    metricName: metrics[0]?.metrics[0]?.name,
    thresholds: threshold
  });

  if (isNil(graphData) && (!isGraphLoading || isMetricsEmpty)) {
    return (
      <Typography className={classes.noDataFound} variant="h5">
        {t(labelNoDataFound)}
      </Typography>
    );
  }

  const filteredGraphData = graphData
    ? {
        ...graphData,
        metrics: graphData.metrics.filter((metric) =>
          equals(metricId, metric.metric_id)
        )
      }
    : graphData;

  const props = {
    baseColor: threshold.baseColor,
    data: filteredGraphData,
    displayAsRaw,
    thresholds: formattedThresholds
  };

  return (
    <Box className={graphClasses.graphContainer}>
      <Typography className={graphClasses.title} variant="h6">
        {metrics[0]?.name}: {metrics[0]?.metrics[0]?.name}
      </Typography>
      <Box className={graphClasses.content}>
        {cond([
          [equals('gauge'), always(<Gauge {...props} />)],
          [equals('bar'), always(<SingleBar {...props} />)],
          [
            T,
            always(
              <GraphText
                {...props}
                labels={{
                  critical: t(labelCritical),
                  warning: t(labelWarning)
                }}
              />
            )
          ]
        ])(singleMetricGraphType)}
      </Box>
    </Box>
  );
};

export default Graph;
