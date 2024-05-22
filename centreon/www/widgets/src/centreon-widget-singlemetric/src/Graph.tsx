import { equals, isNil } from 'ramda';
import { useAtomValue } from 'jotai';

import {
  ContentWithCircularLoading,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import useThresholds from '../../useThresholds';
import { Resource, GlobalRefreshInterval, Metric } from '../../models';
import NoResources from '../../NoResources';
import { areResourcesFullfilled, getWidgetEndpoint } from '../../utils';

import { FormThreshold, SingleMetricGraphType, ValueFormat } from './models';
import { graphEndpoint } from './api/endpoints';
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

  const metricId = metrics[0]?.id;
  const metricName = metrics[0]?.name;

  const baseEndpoint = getWidgetEndpoint({
    dashboardId,
    defaultEndpoint: graphEndpoint,
    isOnPublicPage,
    playlistHash,
    widgetId: id
  });

  const { graphData, isGraphLoading, isMetricsEmpty } = useGraphQuery({
    baseEndpoint,
    bypassMetricsExclusion: true,
    bypassQueryParams: isOnPublicPage,
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
        singleMetricGraphType={displayType}
      />
    </ContentWithCircularLoading>
  );
};

export default Graph;
