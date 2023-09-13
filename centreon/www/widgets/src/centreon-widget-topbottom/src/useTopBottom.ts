import { equals, isEmpty, pluck } from 'ramda';

import {
  buildListingEndpoint,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';

import { metricsTopEndpoint } from './api/endpoint';
import {
  Metric,
  TopBottomSettings,
  WidgetDataResource,
  WidgetResourceType
} from './models';

interface UseTopBottomProps {
  globalRefreshInterval?: number;
  metric: Metric;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<WidgetDataResource>;
  topBottomSettings: TopBottomSettings;
}

const resourceTypeQueryParameter = {
  [WidgetResourceType.host]: 'host.id',
  [WidgetResourceType.hostCategory]: 'hostcategory.id',
  [WidgetResourceType.hostGroup]: 'hostgroup.id',
  [WidgetResourceType.service]: 'service.name'
};

export const areResourcesFullfilled = (
  value: Array<WidgetDataResource>
): boolean =>
  value?.every(
    ({ resourceType, resources }) =>
      !isEmpty(resourceType) && !isEmpty(resources)
  );

const useTopBottom = ({
  globalRefreshInterval,
  refreshInterval,
  refreshIntervalCustom,
  metric,
  topBottomSettings,
  resources
}: UseTopBottomProps): void => {
  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const { data: metricsTop, isFetching } = useFetchQuery({
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: metricsTopEndpoint,
        customQueryParameters: [{ name: 'metric_name', value: metric.name }],
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
      }),
    getQueryKey: () => [
      'topbottom',
      metric?.name,
      JSON.stringify(resources),
      topBottomSettings.numberOfValues,
      topBottomSettings.order
    ],
    queryOptions: {
      enabled: areResourcesFullfilled(resources) && !!metric?.name,
      refetchInterval: refreshIntervalToUse,
      suspense: false
    }
  });

  console.log(metricsTop);
};

export default useTopBottom;
