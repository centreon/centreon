import {
  all,
  equals,
  flatten,
  gt,
  isEmpty,
  length,
  pipe,
  pluck,
  uniq,
  uniqBy
} from 'ramda';

import {
  ListingModel,
  buildListingEndpoint,
  resourceTypeQueryParameter,
  useFetchQuery
} from '@centreon/ui';

import {
  Metric,
  ServiceMetric,
  WidgetDataResource,
  WidgetResourceType
} from '../../../models';

import { serviceMetricsDecoder } from '../../../api/decoders';
import { metricsEndpoint } from '../../../api/endpoints';

interface Props {
  resources: Array<WidgetDataResource>;
  selectedMetrics?: Array<Metric>;
}

interface UseListMetricsState {
  hasMultipleUnitsSelected: boolean;
  hasTooManyMetrics: boolean;
  isLoadingMetrics: boolean;
  metricCount?: number;
  metrics: Array<Metric>;
  servicesMetrics?: ListingModel<ServiceMetric>;
}

export const useListMetrics = ({
  resources,
  selectedMetrics = []
}: Props): UseListMetricsState => {
  const isMetaServiceSelected =
    equals(resources.length, 1) &&
    equals(resources[0].resourceType, WidgetResourceType.metaService);

  const { data: servicesMetrics, isFetching: isLoadingMetrics } = useFetchQuery<
    ListingModel<ServiceMetric>
  >({
    decoder: serviceMetricsDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: metricsEndpoint,
        parameters: {
          limit: 1000,
          search: {
            lists: resources.map((resource) => ({
              field: resourceTypeQueryParameter[resource.resourceType],
              values: equals(resource.resourceType, 'service')
                ? pluck('name', resource.resources)
                : pluck('id', resource.resources)
            }))
          }
        }
      }),
    getQueryKey: () => ['metrics', JSON.stringify(resources)],
    queryOptions: {
      enabled:
        !isEmpty(resources) &&
        all((resource) => !isEmpty(resource.resources), resources) &&
        !isMetaServiceSelected,
      suspense: false
    }
  });

  const hasTooManyMetrics = gt(servicesMetrics?.meta?.total || 0, 1000);

  const metricCount = servicesMetrics?.meta?.total;

  const unitsFromSelectedMetrics = pipe(
    flatten,
    pluck('unit'),
    uniq
  )(selectedMetrics || []);

  const hasMultipleUnitsSelected = gt(length(unitsFromSelectedMetrics), 1);

  const metrics: Array<Metric> = pipe(
    pluck('metrics'),
    flatten,
    uniqBy(({ name }) => name)
  )(servicesMetrics?.result || []);

  return {
    hasMultipleUnitsSelected,
    hasTooManyMetrics,
    isLoadingMetrics,
    metricCount,
    metrics,
    servicesMetrics
  };
};
