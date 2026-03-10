import {
  ContentWithCircularLoading,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { equals, isNil, last } from 'ramda';

import { GlobalRefreshInterval, Metric, Resource } from '../../models';
import NoResources from '../../NoResources';
import useThresholds from '../../useThresholds';
import {
  areResourcesFullfilled,
  getIsMetaServiceSelected,
  getWidgetEndpoint
} from '../../utils';
import { selectEndpoint } from './api/endpoints';
import { FormThreshold, SingleMetricGraphType, ValueFormat } from './models';
import SingleMetricRenderer from './SingleMetricRenderer';

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

    if (isMetaServiceSelected) {
      return resources[0]?.resources[0]?.id;
    }

    return metrics.find(({ serviceName }) => equals(serviceName, service?.name))
      ?.serviceId;
  };

  const hostId = last(
    resources.find(({ resourceType }) => !equals(resourceType, 'service'))
      ?.resources || []
  )?.id;

  const baseEndpoint = getWidgetEndpoint({
    dashboardId,
    defaultEndpoint: selectEndpoint({
      hostId,
      idForService: getServiceId(),
      isMetaServiceSelected,
      metricName
    }),
    displayType,
    isOnPublicPage,
    playlistHash,
    widgetId: id
  });

  const { graphData, isGraphLoading, isMetricsEmpty } = useGraphQuery({
    baseEndpoint,
    bypassMetricsExclusion: true,
    bypassQueryParams: true,
    isEnabled: Boolean(hostId && (getServiceId() || isMetaServiceSelected)),
    metrics,
    prefix: widgetPrefixQuery,
    refreshCount,
    refreshInterval: refreshIntervalToUse,
    resources
  });

  const displayAsRaw = equals('raw')(valueFormat);

  const formattedGraphData = graphData
    ? {
        ...graphData,
        metrics: graphData?.metrics?.map((metric) => ({
          ...metric,
          data: [metric?.current_value]
        }))
      }
    : undefined;

  const formattedThresholds = useThresholds({
    data: formattedGraphData,
    displayAsRaw,
    isMetaServiceSelected,
    metricName,
    thresholds: threshold
  });

  const areResourcesOk = areResourcesFullfilled(resources);

  if (
    !areResourcesOk ||
    (!isMetaServiceSelected && isMetricsEmpty) ||
    (isFromPreview && isGraphLoading && isNil(graphData))
  ) {
    return <NoResources />;
  }

  const filteredGraphData = formattedGraphData
    ? {
        ...formattedGraphData,
        metrics: isMetaServiceSelected
          ? formattedGraphData.metrics
          : formattedGraphData.metrics.filter((metric) =>
              equals(metricId, metric.metric_id)
            )
      }
    : formattedGraphData;

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
