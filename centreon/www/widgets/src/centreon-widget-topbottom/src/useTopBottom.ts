import { equals, isNil, pluck } from 'ramda';

import {
  buildListingEndpoint,
  resourceTypeQueryParameter,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';

import { GlobalRefreshInterval, Metric, Resource } from '../../models';
import { areResourcesFullfilled } from '../../utils';

import { metricsTopEndpoint } from './api/endpoint';
import { MetricsTop, TopBottomSettings } from './models';
import { metricsTopDecoder } from './api/decoder';

interface UseTopBottomProps {
  globalRefreshInterval: GlobalRefreshInterval;
  metrics: Array<Metric>;
  refreshCount: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<Resource>;
  topBottomSettings: TopBottomSettings;
}

interface UseTopBottomState {
  isLoading: boolean;
  isMetricEmpty: boolean;
  metricsTop?: MetricsTop;
}

const useTopBottom = ({
  globalRefreshInterval,
  refreshInterval,
  refreshIntervalCustom,
  metrics,
  topBottomSettings,
  resources,
  refreshCount
}: UseTopBottomProps): UseTopBottomState => {
  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const metricName = metrics?.[0]?.name;

  const { data: metricsTop, isFetching } = useFetchQuery<MetricsTop>({
    decoder: metricsTopDecoder,
    getEndpoint: () =>
      `${buildListingEndpoint({
        baseEndpoint: metricsTopEndpoint,
        parameters: {
          limit: topBottomSettings.numberOfValues,
          search: {
            lists: resources.map((resource) => ({
              field: resourceTypeQueryParameter[resource.resourceType],
              values: equals(resource.resourceType, 'service')
                ? pluck('name', resource.resources)
                : pluck('id', resource.resources)
            }))
          },
          sort: {
            current_value: equals(topBottomSettings.order, 'bottom')
              ? 'DESC'
              : 'ASC'
          }
        }
      })}&metric_name=${encodeURIComponent(metricName)}`,
    getQueryKey: () => [
      'topbottom',
      metricName,
      JSON.stringify(resources),
      topBottomSettings.numberOfValues,
      topBottomSettings.order,
      refreshCount
    ],
    queryOptions: {
      enabled: areResourcesFullfilled(resources) && !!metricName,
      refetchInterval: refreshIntervalToUse,
      suspense: false
    }
  });

  return {
    isLoading: isFetching && !metricsTop,
    isMetricEmpty: isNil(metricName),
    metricsTop
  };
};

export default useTopBottom;
