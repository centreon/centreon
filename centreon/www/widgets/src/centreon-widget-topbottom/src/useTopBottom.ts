import { useAtomValue } from 'jotai';
import { equals, isNil, pluck } from 'ramda';

import {
  buildListingEndpoint,
  resourceTypeQueryParameter,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import {
  CommonWidgetProps,
  GlobalRefreshInterval,
  Metric,
  Resource
} from '../../models';
import {
  areResourcesFullfilled,
  getIsMetaServiceSelected,
  getWidgetEndpoint
} from '../../utils';

import { metricsTopDecoder } from './api/decoder';
import { metricsTopEndpoint } from './api/endpoint';
import { MetricsTop, TopBottomSettings } from './models';

interface UseTopBottomProps
  extends Pick<
    CommonWidgetProps<object>,
    'playlistHash' | 'dashboardId' | 'id' | 'widgetPrefixQuery'
  > {
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
  refreshCount,
  dashboardId,
  id,
  playlistHash,
  widgetPrefixQuery
}: UseTopBottomProps): UseTopBottomState => {
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const metricName = metrics?.[0]?.name;

  const isMetaServiceSelected = getIsMetaServiceSelected(resources);

  const { data: metricsTop, isFetching } = useFetchQuery<MetricsTop>({
    decoder: metricsTopDecoder,
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: `${buildListingEndpoint({
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
        })}${isMetaServiceSelected ? '' : `&metric_name=${encodeURIComponent(metricName)}`}`,
        isOnPublicPage,
        playlistHash,
        widgetId: id
      }),
    getQueryKey: () => [
      widgetPrefixQuery,
      'topbottom',
      metricName,
      JSON.stringify(resources),
      topBottomSettings.numberOfValues,
      topBottomSettings.order,
      refreshCount
    ],
    queryOptions: {
      enabled:
        areResourcesFullfilled(resources) &&
        (isMetaServiceSelected || !!metricName),
      refetchInterval: refreshIntervalToUse,
      suspense: false
    }
  });

  return {
    isLoading: isFetching && !metricsTop,
    isMetricEmpty: !isMetaServiceSelected && isNil(metricName),
    metricsTop
  };
};

export default useTopBottom;
