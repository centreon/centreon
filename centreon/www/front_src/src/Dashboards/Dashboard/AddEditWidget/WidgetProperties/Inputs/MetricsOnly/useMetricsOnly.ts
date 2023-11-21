import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  all,
  equals,
  flatten,
  gt,
  includes,
  isEmpty,
  isNil,
  length,
  pipe,
  pluck,
  propEq,
  reject,
  uniq,
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
  WidgetDataResource
} from '../../../models';
import { serviceMetricsDecoder } from '../../../api/decoders';
import { metricsEndpoint } from '../../../api/endpoints';
import { getDataProperty, resourceTypeQueryParameter } from '../utils';
import { labelIncludesXHost } from '../../../../translatedLabels';

interface UseMetricsOnlyState {
  changeMetric: (_, newMetric: SelectEntry | null) => void;
  changeMetrics: (_, newMetrics: Array<SelectEntry> | null) => void;
  deleteMetricItem: (index) => void;
  getMetricOptionDisabled: (metricOption) => boolean;
  getMultipleOptionLabel: (metric) => string;
  getOptionLabel: (metric) => string;
  hasNoResources: () => boolean;
  hasTooManyMetrics: boolean;
  isLoadingMetrics: boolean;
  metricCount?: number;
  metrics: Array<Metric>;
  resources: Array<WidgetDataResource>;
  selectedMetrics?: Array<Metric>;
}

const useMetricsOnly = (propertyName: string): UseMetricsOnlyState => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } = useFormikContext<Widget>();

  const resources = (values.data?.resources || []) as Array<WidgetDataResource>;

  const value = useMemo<Array<Metric> | undefined>(
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
        all((resource) => !isEmpty(resource.resources), resources),
      keepPreviousData: true,
      suspense: false
    }
  });

  const hasTooManyMetrics = gt(servicesMetrics?.meta?.total || 0, 1000);

  const metricCount = servicesMetrics?.meta?.total;

  const unitsFromSelectedMetrics = pipe(
    flatten,
    pluck('unit'),
    uniq
  )([value] || []);

  const hasReachedTheLimitOfUnits = equals(length(unitsFromSelectedMetrics), 2);

  const metrics = pipe(
    pluck('metrics'),
    flatten,
    uniqBy(({ name }) => name)
  )(servicesMetrics?.result || []);

  const changeMetric = (_, newMetric: SelectEntry | null): void => {
    setFieldValue(`data.${propertyName}`, [newMetric]);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const deleteMetricItem = (option): void => {
    const newMetric = reject(propEq(option.id, 'id'), value || []);

    setFieldValue(`data.${propertyName}`, newMetric);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const changeMetrics = (_, newMetrics: Array<SelectEntry> | null): void => {
    setFieldValue(`data.${propertyName}`, newMetrics || []);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const getMetricOptionDisabled = (metricOption): boolean => {
    if (!hasReachedTheLimitOfUnits) {
      return false;
    }

    return !includes(metricOption.unit, unitsFromSelectedMetrics);
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

  const getMultipleOptionLabel = (metric): string => {
    if (isNil(metric)) {
      return '';
    }

    return `${metric.name} (${
      metric.unit
    }) / ${getNumberOfResourcesRelatedToTheMetric(metric.name)}`;
  };

  return {
    changeMetric,
    changeMetrics,
    deleteMetricItem,
    getMetricOptionDisabled,
    getMultipleOptionLabel,
    getOptionLabel,
    hasNoResources,
    hasTooManyMetrics,
    isLoadingMetrics,
    metricCount,
    metrics,
    resources,
    selectedMetrics: value
  };
};

export default useMetricsOnly;
