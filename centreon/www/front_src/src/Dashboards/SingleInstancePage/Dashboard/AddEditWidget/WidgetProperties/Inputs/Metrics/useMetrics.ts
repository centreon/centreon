import { useMemo, useEffect } from 'react';

import { useFormikContext } from 'formik';
import {
  equals,
  identity,
  includes,
  innerJoin,
  isEmpty,
  isNil,
  omit,
  pluck,
  propEq,
  reject
} from 'ramda';
import { useAtomValue } from 'jotai';

import { SelectEntry, useDeepCompare } from '@centreon/ui';

import {
  FormMetric,
  Metric,
  ServiceMetric,
  Widget,
  WidgetDataResource
} from '../../../models';
import { getDataProperty } from '../utils';
import { singleHostPerMetricAtom } from '../../../atoms';

import { useListMetrics } from './useListMetrics';
import { useRenderOptions } from './useRenderOptions';

interface UseMetricsOnlyState {
  changeMetric: (_, newMetric: SelectEntry | null) => void;
  changeMetrics: (_, newMetrics: Array<SelectEntry> | null) => void;
  deleteMetricItem: (index) => void;
  error?: string;
  getMetricOptionDisabled: (metricOption) => boolean;
  getOptionLabel: (metric: FormMetric) => string;
  getTagLabel: (metric: FormMetric) => string;
  hasNoResources: () => boolean;
  hasReachedTheLimitOfUnits: boolean;
  hasTooManyMetrics: boolean;
  isLoadingMetrics: boolean;
  isTouched?: boolean;
  metricCount?: number;
  metricWithSeveralResources?: false | string;
  metrics: Array<Metric>;
  renderOptionsForMultipleMetricsAndResources: (
    _,
    option: FormMetric
  ) => JSX.Element;
  renderOptionsForSingleMetric: (_, option: FormMetric) => JSX.Element;
  resources: Array<WidgetDataResource>;
  selectedMetrics?: Array<Metric>;
}

const useMetrics = (propertyName: string): UseMetricsOnlyState => {
  const { values, setFieldValue, setFieldTouched, errors, touched } =
    useFormikContext<Widget>();

  const singleHostPerMetric = useAtomValue(singleHostPerMetricAtom);

  const resources = (values.data?.resources || []) as Array<WidgetDataResource>;

  const value = useMemo<Array<FormMetric> | undefined>(
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

  const {
    hasReachedTheLimitOfUnits,
    hasTooManyMetrics,
    isLoadingMetrics,
    metrics,
    metricCount,
    unitsFromSelectedMetrics,
    servicesMetrics
  } = useListMetrics({ resources, selectedMetrics: value });

  const getResourcesByMetricName = (
    metricName: string
  ): Array<{ metricId?: number } & Omit<ServiceMetric, 'metrics'>> => {
    const resourcesByMetricName = (servicesMetrics?.result || []).map(
      (service) =>
        service.metrics.find((metric) => equals(metric.name, metricName))
          ? {
              ...omit(['metrics'], service),
              metricId: service.metrics.find((metric) =>
                equals(metric.name, metricName)
              )?.id
            }
          : null,
      []
    );

    return resourcesByMetricName.filter(identity) as Array<
      { metricId?: number } & Omit<ServiceMetric, 'metrics'>
    >;
  };

  const {
    renderOptionsForSingleMetric,
    renderOptionsForMultipleMetricsAndResources
  } = useRenderOptions({
    getResourcesByMetricName,
    propertyName,
    value: value || []
  });

  const changeMetric = (_, newMetric: SelectEntry | null): void => {
    setFieldValue(`data.${propertyName}`, [
      {
        ...newMetric,
        excludedMetrics: [],
        includeAllMetrics: true
      }
    ]);
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

  const getTagLabel = (metric: FormMetric): string => {
    if (isNil(metric)) {
      return '';
    }

    const metricResources = getResourcesByMetricName(metric.name);

    const resourcesWithoutExcludedMetrics = reject(
      ({ metricId }) => metric.excludedMetrics.includes(metricId),
      metricResources
    );

    return `${metric.name} (${metric.unit})/${resourcesWithoutExcludedMetrics.length}`;
  };

  const getOptionLabel = (metric: FormMetric): string => {
    if (isNil(metric)) {
      return '';
    }

    return `${metric.name} (${metric.unit})`;
  };

  const getNumberOfResourcesRelatedToTheMetric = (metricName: string): number =>
    (servicesMetrics?.result || []).reduce(
      (acc, service) =>
        acc +
        (service.metrics.find((metric) => equals(metric.name, metricName))
          ? 1
          : 0),
      0
    );

  const getFirstUsedResourceForMetric = (
    metricName?: string
  ): string | undefined => {
    if (!metricName) {
      return undefined;
    }

    return (servicesMetrics?.result || []).filter((service) =>
      service.metrics.filter((metric) => equals(metric.name, metricName))
    )[0].name;
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

      const baseMetricNames = pluck('name', metrics);
      const baseMetricIds = pluck('id', servicesMetrics?.result || []);

      const intersectionBetweenMetricsIdsAndValues = innerJoin(
        (metric, name) => equals(metric.name, name),
        value || [],
        baseMetricNames
      );

      const finalIntersection = intersectionBetweenMetricsIdsAndValues.map(
        (item) => {
          return {
            ...item,
            excludedMetrics: item.excludedMetrics.filter((metric) =>
              baseMetricIds.includes(metric)
            )
          };
        }
      );

      setFieldValue(
        `data.${propertyName}`,
        isEmpty(finalIntersection) ? [] : finalIntersection
      );
    },
    useDeepCompare([servicesMetrics])
  );

  return {
    changeMetric,
    changeMetrics,
    deleteMetricItem,
    error,
    getMetricOptionDisabled,
    getOptionLabel,
    getTagLabel,
    hasNoResources,
    hasReachedTheLimitOfUnits,
    hasTooManyMetrics,
    isLoadingMetrics,
    isTouched,
    metricCount,
    metricWithSeveralResources,
    metrics,
    renderOptionsForMultipleMetricsAndResources,
    renderOptionsForSingleMetric,
    resources,
    selectedMetrics: value
  };
};

export default useMetrics;
