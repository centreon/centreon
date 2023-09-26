import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  all,
  equals,
  flatten,
  gt,
  isEmpty,
  isNil,
  pipe,
  pluck,
  uniqBy
} from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  ListingModel,
  SelectEntry,
  buildListingEndpoint,
  useFetchQuery
} from '@centreon/ui';

import {
  Metric,
  ServiceMetric,
  Widget,
  WidgetDataResource,
  WidgetResourceType
} from '../../../models';
import { serviceMetricsDecoder } from '../../../api/decoders';
import { metricsEndpoint } from '../../../api/endpoints';
import { getDataProperty } from '../utils';
import { labelIncludesXHost } from '../../../../translatedLabels';

const resourceTypeQueryParameter = {
  [WidgetResourceType.host]: 'host.id',
  [WidgetResourceType.hostCategory]: 'hostcategory.id',
  [WidgetResourceType.hostGroup]: 'hostgroup.id',
  [WidgetResourceType.service]: 'service.name'
};

interface UseMetricsOnlyState {
  changeMetric: (_, newMetric: SelectEntry | null) => void;
  getOptionLabel: (metric) => string;
  getSelectedItemLabel: (metric) => string;
  hasNoResources: () => boolean;
  hasTooManyMetrics: boolean;
  isLoadingMetrics: boolean;
  metricCount?: number;
  metrics: Array<Metric>;
  resources: Array<WidgetDataResource>;
  selectedMetric?: Metric;
}

const useMetricsOnly = (propertyName: string): UseMetricsOnlyState => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } = useFormikContext<Widget>();

  const resources = (values.data?.resources || []) as Array<WidgetDataResource>;

  const value = useMemo<Metric | undefined>(
    () => getDataProperty({ obj: values, propertyName }),
    [getDataProperty({ obj: values, propertyName })]
  );

  const { data: servicesMetrics, isFetching: isLoadingMetrics } = useFetchQuery<
    ListingModel<ServiceMetric>
  >({
    decoder: serviceMetricsDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: metricsEndpoint,
        parameters: {
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
        all((resource) => !isEmpty(resource.resources), resources),
      keepPreviousData: true,
      suspense: false
    }
  });

  const hasTooManyMetrics = gt(servicesMetrics?.meta?.total || 0, 1000);

  const metricCount = servicesMetrics?.meta?.total;

  const metrics = pipe(
    pluck('metrics'),
    flatten,
    uniqBy(({ name }) => name)
  )(servicesMetrics?.result || []);

  const changeMetric = (_, newMetric: SelectEntry | null): void => {
    setFieldValue(`data.${propertyName}`, newMetric);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const hasNoResources = (): boolean => {
    if (!resources.length) {
      return true;
    }

    return resources.every((resource) => !resource.resources.length);
  };

  const getOptionLabel = (metric): string => {
    if (isNil(metric)) {
      return '';
    }

    return `${metric.name} (${metric.unit}) / ${t(labelIncludesXHost, {
      count: getNumberOfResourcesRelatedToTheMetric(metric.name)
    })}`;
  };

  const getNumberOfResourcesRelatedToTheMetric = (metricName: string): number =>
    (servicesMetrics?.result || []).reduce(
      (acc, service) =>
        acc +
        service.metrics.filter((metric) => equals(metric.name, metricName))
          .length,
      0
    );

  return {
    changeMetric,
    getOptionLabel,
    hasNoResources,
    hasTooManyMetrics,
    isLoadingMetrics,
    metricCount,
    metrics,
    resources,
    selectedMetric: value
  };
};

export default useMetricsOnly;
