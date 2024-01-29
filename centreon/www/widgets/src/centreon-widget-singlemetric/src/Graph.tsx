import { equals, isNil } from 'ramda';

import {
  ContentWithCircularLoading,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';

import useThresholds from '../../useThresholds';
import { Resource, ServiceMetric, GlobalRefreshInterval } from '../../models';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';

import { FormThreshold, SingleMetricGraphType, ValueFormat } from './models';
import { graphEndpoint } from './api/endpoints';
import SingleMetricRenderer from './SingleMetricRenderer';

interface Props {
  globalRefreshInterval: GlobalRefreshInterval;
  isFromPreview;
  metrics: Array<ServiceMetric>;
  refreshCount: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<Resource>;
  singleMetricGraphType: SingleMetricGraphType;
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
  resources,
  isFromPreview
}: Props): JSX.Element => {
  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const metricId = metrics[0]?.id;
  const metricName = metrics[0]?.name;

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
    metricName,
    thresholds: threshold
  });

  const areResourcesOk = areResourcesFullfilled(resources);

  if (
    !areResourcesOk ||
    isMetricsEmpty ||
    (isFromPreview && isGraphLoading && isNil(graphData))
  ) {
    return <NoResources />;
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
    <ContentWithCircularLoading
      alignCenter
      loading={isFromPreview && isGraphLoading}
    >
      <SingleMetricRenderer
        graphProps={props}
        singleMetricGraphType={singleMetricGraphType}
      />
    </ContentWithCircularLoading>
  );
};

export default Graph;
