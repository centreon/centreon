import { useMemo, useEffect } from 'react';

import { useFormikContext } from 'formik';
import {
  equals,
  includes,
  innerJoin,
  isEmpty,
  isNil,
  pluck,
  propEq,
  reject
} from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { SelectEntry, useDeepCompare } from '@centreon/ui';

import { Metric, Widget, WidgetDataResource } from '../../../models';
import { getDataProperty } from '../utils';
import { labelIncludesXHost } from '../../../../translatedLabels';
import { singleHostPerMetricAtom } from '../../../atoms';

import { useListMetrics } from './useListMetrics';

interface UseMetricsOnlyState {
  changeMetric: (_, newMetric: SelectEntry | null) => void;
  changeMetrics: (_, newMetrics: Array<SelectEntry> | null) => void;
  deleteMetricItem: (index) => void;
  error?: string;
  getMetricOptionDisabled: (metricOption) => boolean;
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

  const {
    hasReachedTheLimitOfUnits,
    hasTooManyMetrics,
    isLoadingMetrics,
    metrics,
    metricCount,
    unitsFromSelectedMetrics,
    servicesMetrics
  } = useListMetrics({ resources, selectedMetrics: value });

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

      const intersectionBetweenMetricsIdsAndValues = innerJoin(
        (metric, name) => equals(metric.name, name),
        value || [],
        baseMetricNames
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

  return {
    changeMetric,
    changeMetrics,
    deleteMetricItem,
    error,
    getMetricOptionDisabled,
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
