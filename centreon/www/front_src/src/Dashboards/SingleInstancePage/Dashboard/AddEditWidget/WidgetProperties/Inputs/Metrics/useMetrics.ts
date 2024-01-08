import { useMemo, useEffect } from 'react';

import { useFormikContext } from 'formik';
import {
  all,
  equals,
  flatten,
  gt,
  includes,
  innerJoin,
  isEmpty,
  isNil,
  length,
  map,
  pick,
  pipe,
  pluck,
  propEq,
  reject,
  uniq,
  uniqBy
} from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import {
  ListingModel,
  SelectEntry,
  buildListingEndpoint,
  useDeepCompare,
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
import { singleHostPerMetricAtom } from '../../../atoms';

interface UseMetricsOnlyState {
  changeMetric: (_, newMetric: SelectEntry | null) => void;
  changeMetrics: (_, newMetrics: Array<SelectEntry> | null) => void;
  deleteMetricItem: (index) => void;
  error?: string;
  getMetricOptionDisabled: (metricOption) => boolean;
  getMultipleOptionLabel: (metric) => string;
  getOptionLabel: (metric) => string;
  hasNoResources: () => boolean;
  hasReachedTheLimitOfUnits: boolean;
  hasTooManyMetrics: boolean;
  isLoadingMetrics: boolean;
  isTouched?: boolean;
  metricCount?: number;
  metricWithSeveralResources?: false | string;
  metrics: Array<Metric>;
  resources: Array<WidgetDataResource>;
  selectedMetrics?: Array<Metric>;
}

const useMetrics = (propertyName: string): UseMetricsOnlyState => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched, errors, touched } =
    useFormikContext<Widget>();

  const singleHostPerMetric = useAtomValue(singleHostPerMetricAtom);

  const resources = (values.data?.resources || []) as Array<WidgetDataResource>;

  const value = useMemo<Array<Metric> | undefined>(
    () => getDataProperty({ obj: values, propertyName }),
    [getDataProperty({ obj: values, propertyName })]
  );

  const error = useMemo<string | undefined>(
    () => getDataProperty({ obj: errors, propertyName }),
    [getDataProperty({ obj: errors, propertyName })]
  );

  const isTouched = useMemo<boolean | undefined>(
    () => getDataProperty({ obj: touched, propertyName }),
    [getDataProperty({ obj: touched, propertyName })]
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

  const metrics: Array<Metric> = pipe(
    pluck('metrics'),
    flatten,
    uniqBy(({ name }) => name)
  )(servicesMetrics?.result || []);

  const services = map(
    pick(['uuid', 'id', 'name', 'parentName']),
    servicesMetrics?.result || []
  );

  const changeMetric = (_, newMetric: SelectEntry | null): void => {
    setFieldValue(`data.${propertyName}`, [newMetric]);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const deleteMetricItem = (option): void => {
    const newMetrics = reject(propEq(option.id, 'id'), value || []);

    setFieldValue(`data.${propertyName}`, newMetrics);
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

  const getFirstUsedResourceForMetric = (
    metricName?: string
  ): string | undefined => {
    if (!metricName) {
      return undefined;
    }

    const firstUsedResource = (servicesMetrics?.result || []).filter(
      (service) =>
        service.metrics.filter((metric) => equals(metric.name, metricName))
    )[0];

    return `${firstUsedResource.parentName}_${firstUsedResource.name}`;
  };

  const getMultipleOptionLabel = (metric): string => {
    if (isNil(metric)) {
      return '';
    }

    return `${metric.name} (${
      metric.unit
    }) / ${getNumberOfResourcesRelatedToTheMetric(metric.name)}`;
  };

  const metricWithSeveralResources =
    singleHostPerMetric &&
    value?.some(
      ({ name }) => getNumberOfResourcesRelatedToTheMetric(name) > 1
    ) &&
    getFirstUsedResourceForMetric(value[0].name);

  useEffect(
    () => {
      if (isNil(servicesMetrics)) {
        return;
      }

      if (isEmpty(resources)) {
        setFieldValue(`data.${propertyName}`, []);

        return;
      }

      const baseMetricIds = pluck('id', metrics);

      const intersectionBetweenMetricsIdsAndValues = innerJoin(
        (metric, id) => equals(metric.id, id),
        value || [],
        baseMetricIds
      );

      setFieldValue(
        `data.${propertyName}`,
        isEmpty(intersectionBetweenMetricsIdsAndValues)
          ? []
          : intersectionBetweenMetricsIdsAndValues
      );
    },
    useDeepCompare([servicesMetrics, resources])
  );

  useEffect(() => {
    setFieldValue(`data.services`, services);
  }, [values?.data?.[propertyName]]);

  return {
    changeMetric,
    changeMetrics,
    deleteMetricItem,
    error,
    getMetricOptionDisabled,
    getMultipleOptionLabel,
    getOptionLabel,
    hasNoResources,
    hasReachedTheLimitOfUnits,
    hasTooManyMetrics,
    isLoadingMetrics,
    isTouched,
    metricCount,
    metricWithSeveralResources,
    metrics,
    resources,
    selectedMetrics: value
  };
};

export default useMetrics;
