import { useAtomValue } from 'jotai';
import { equals, isNil, last } from 'ramda';

import {
  ContentWithCircularLoading,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import NoResources from '../../NoResources';
import { GlobalRefreshInterval, Metric, Resource } from '../../models';
import useThresholds from '../../useThresholds';
import {
  areResourcesFullfilled,
  getIsMetaServiceSelected,
  getWidgetEndpoint
} from '../../utils';

import SingleMetricRenderer from './SingleMetricRenderer';
import { getMetricsEndpoint } from './api/endpoints';
import { FormThreshold, SingleMetricGraphType, ValueFormat } from './models';

interface Props {
  dashboardId: number | string;
  displayType: SingleMetricGraphType;
  globalRefreshInterval: GlobalRefreshInterval;
  id: string;
  isFromPreview;
  metrics: Array<Metric>;
  playlistHash?: string;
  refreshCount: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<Resource>;
  threshold: FormThreshold;
  valueFormat: ValueFormat;
  widgetPrefixQuery: string;
}

const Graph = ({
  metrics,
  displayType,
  threshold,
  refreshInterval,
  refreshIntervalCustom,
  globalRefreshInterval,
  valueFormat,
  refreshCount,
  resources,
  isFromPreview,
  playlistHash,
  dashboardId,
  id,
  widgetPrefixQuery
}: Props): JSX.Element => {
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);
  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const isMetaServiceSelected = getIsMetaServiceSelected(resources);

  const metricId = metrics[0]?.id;
  const metricName = metrics[0]?.name;

  const getServiceId = () => {
    const service = last(
      resources.find(({ resourceType }) => equals(resourceType, 'service'))
        ?.resources || []
    );

    return metrics.find(({ serviceName }) => equals(serviceName, service?.name))
      ?.serviceId;
  };

  const hostId = last(
    resources.find(({ resourceType }) => !equals(resourceType, 'service'))
      ?.resources || []
  )?.id;

  const baseEndpoint = getWidgetEndpoint({
    dashboardId,
    defaultEndpoint: getMetricsEndpoint({
      hostId,
      serviceId: getServiceId(),
      metricName
    }),
    isOnPublicPage,
    playlistHash,
    widgetId: id
  });

  const { graphData, isGraphLoading, isMetricsEmpty } = useGraphQuery({
    baseEndpoint,
    bypassMetricsExclusion: true,
    bypassQueryParams: true,
    metrics,
    prefix: widgetPrefixQuery,
    refreshCount,
    refreshInterval: refreshIntervalToUse,
    resources
  });

  const displayAsRaw = equals('raw')(valueFormat);

  const formattedThresholds = useThresholds({
    data: graphData,
    displayAsRaw,
    metricName,
    thresholds: threshold,
    isMetaServiceSelected
  });

  const areResourcesOk = areResourcesFullfilled(resources);

  if (
    !areResourcesOk ||
    (!isMetaServiceSelected && isMetricsEmpty) ||
    (isFromPreview && isGraphLoading && isNil(graphData))
  ) {
    return <NoResources />;
  }

  const filteredGraphData = graphData
    ? {
        ...graphData,
        metrics: isMetaServiceSelected
          ? graphData.metrics.map(metric=>({...metric, data:[metric.current_value]}))
          : graphData.metrics.filter((metric) =>
              equals(metricId, metric.metric_id)
            ).map(metric=>({...metric, data:[metric.current_value]}))
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
      loading={(isFromPreview && isGraphLoading) || false}
    >
      <SingleMetricRenderer
        graphProps={props}
        singleMetricGraphType={displayType}
      />
    </ContentWithCircularLoading>
  );
};

export default Graph;
