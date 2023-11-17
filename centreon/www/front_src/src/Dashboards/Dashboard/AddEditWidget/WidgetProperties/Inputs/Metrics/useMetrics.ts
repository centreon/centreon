import { ChangeEvent, useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  all,
  equals,
  find,
  flatten,
  gt,
  includes,
  innerJoin,
  isEmpty,
  isNil,
  length,
  pipe,
  pluck,
  propEq,
  reject,
  uniq
} from 'ramda';
import { useAtomValue } from 'jotai';

import {
  ListingModel,
  SelectEntry,
  buildListingEndpoint,
  useDeepCompare,
  useFetchQuery
} from '@centreon/ui';

import {
  ServiceMetric,
  Widget,
  WidgetDataMetric,
  WidgetDataResource
} from '../../../models';
import { metricsEndpoint } from '../../../api/endpoints';
import { serviceMetricsDecoder } from '../../../api/decoders';
import { labelPleaseSelectAMetric } from '../../../../translatedLabels';
import { singleMetricSelectionAtom } from '../../../atoms';
import { getDataProperty, resourceTypeQueryParameter } from '../utils';

interface UseMetricsState {
  addButtonHidden?: boolean;
  addMetric: () => void;
  changeMetric: (index) => (_, newMetrics: SelectEntry | null) => void;
  changeMetrics: (index) => (_, newMetrics: Array<SelectEntry> | null) => void;
  changeService: (index) => (e: ChangeEvent<HTMLInputElement>) => void;
  deleteMetric: (index: number | string) => () => void;
  deleteMetricItem: (index, option) => void;
  error: string | null;
  getMetricOptionDisabled: (metricOption) => boolean;
  getMetricsFromService: (serviceId: number) => Array<SelectEntry>;
  getOptionLabel: (metric) => string;
  hasNoResources: () => boolean;
  hasOnlyOneService: boolean;
  hasReachedTheLimitOfUnits: boolean;
  hasTooManyMetrics: boolean;
  isLoadingMetrics: boolean;
  metricCount: number | undefined;
  resources: Array<WidgetDataResource>;
  serviceOptions: Array<SelectEntry>;
  value: Array<WidgetDataMetric>;
}

const useMetrics = (propertyName: string): UseMetricsState => {
  const { values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<Widget>();

  const singleMetricSection = useAtomValue(singleMetricSelectionAtom);

  const resources = (values.data?.resources || []) as Array<WidgetDataResource>;

  const { data: servicesMetrics, isFetching: isLoadingMetrics } = useFetchQuery<
    ListingModel<ServiceMetric>
  >({
    decoder: serviceMetricsDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: metricsEndpoint,
        parameters: {
          limit: 100,
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

  const value = useMemo<Array<WidgetDataMetric> | undefined>(
    () => getDataProperty({ obj: values, propertyName }),
    [getDataProperty({ obj: values, propertyName })]
  );

  const isTouched = useMemo<boolean | undefined>(
    () => getDataProperty({ obj: touched, propertyName }),
    [getDataProperty({ obj: touched, propertyName })]
  );

  const hasTooManyMetrics = gt(servicesMetrics?.meta?.total || 0, 100);

  const serviceOptions = useMemo<Array<SelectEntry>>(
    () =>
      (servicesMetrics?.result || []).map(({ id, name }) => ({
        id,
        name
      })),
    [servicesMetrics?.result]
  );

  const metricCount = servicesMetrics?.meta?.total;

  const errorToDisplay =
    isTouched && isEmpty(value) ? labelPleaseSelectAMetric : null;

  const unitsFromSelectedMetrics = pipe(
    pluck('metrics'),
    flatten,
    pluck('unit'),
    uniq
  )(value || []);

  const hasReachedTheLimitOfUnits = equals(length(unitsFromSelectedMetrics), 2);

  const hasOnlyOneService = servicesMetrics
    ? equals(length(servicesMetrics.result || []), 1)
    : false;

  const hasNoResources = (): boolean => {
    if (!resources.length) {
      return true;
    }

    return resources.every((resource) => !resource.resources.length);
  };

  const addMetric = (): void => {
    setFieldValue(`data.${propertyName}`, [
      ...(value || []),
      {
        id: '',
        metrics: [],
        name: ''
      }
    ]);
  };

  const deleteMetric = (index: number | string) => (): void => {
    const newServiceMetrics = (value || []).filter((_, i) => !equals(i, index));
    setFieldValue(`data.${propertyName}`, newServiceMetrics);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const getMetricsFromService = (id: number): Array<SelectEntry> => {
    return (
      (servicesMetrics?.result || []).find((metric) => equals(metric.id, id))
        ?.metrics || []
    );
  };

  const getMetricOptionDisabled = (metricOption): boolean => {
    if (singleMetricSection) {
      const metrics = pipe(
        pluck('metrics'),
        flatten,
        pluck('name')
      )(value || []);

      return (
        singleMetricSection &&
        gt(metrics.length, 0) &&
        !metrics.includes(metricOption.name)
      );
    }

    if (!hasReachedTheLimitOfUnits) {
      return false;
    }

    return !includes(metricOption.unit, unitsFromSelectedMetrics);
  };

  const getOptionLabel = (metric): string => {
    return `${metric.name} (${metric.unit})`;
  };

  const changeService =
    (index) =>
    (e: ChangeEvent<HTMLInputElement>): void => {
      const serviceName = find(
        (service) => equals(service.id, e.target.value),
        serviceOptions
      )?.name;
      setFieldValue(`data.${propertyName}.${index}.id`, e.target.value);
      setFieldValue(`data.${propertyName}.${index}.name`, serviceName);
      setFieldValue(`data.${propertyName}.${index}.metrics`, []);
    };

  const changeMetrics =
    (index) =>
    (_, newMetrics: Array<SelectEntry> | null): void => {
      setFieldValue(`data.${propertyName}.${index}.metrics`, newMetrics || []);
      setFieldTouched(`data.${propertyName}`, true, false);
    };

  const deleteMetricItem = (index, option): void => {
    const newMetric = reject(propEq(option.id, 'id'), value || []);

    setFieldValue(`data.${propertyName}.${index}.metrics`, newMetric);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const changeMetric =
    (index) =>
    (_, newMetrics: SelectEntry | null): void => {
      setFieldValue(
        `data.${propertyName}.${index}.metrics`,
        newMetrics ? [newMetrics] : []
      );
      setFieldTouched(`data.${propertyName}`, true, false);
    };

  useEffect(
    () => {
      if (isNil(servicesMetrics)) {
        return;
      }

      if (isEmpty(resources)) {
        setFieldValue(`data.${propertyName}`, []);

        return;
      }

      if (
        equals(servicesMetrics.result.length, 1) &&
        isEmpty(value?.[0].id) &&
        isEmpty(value?.[0].metrics)
      ) {
        setFieldValue(`data.${propertyName}`, [
          {
            id: servicesMetrics.result[0].id,
            metrics: [],
            name: servicesMetrics.result[0].name
          }
        ]);

        return;
      }

      const baseServiceIds = pluck('id', servicesMetrics?.result || []);

      const intersectionBetweenServicesIdsAndValues = innerJoin(
        (service, id) => equals(service.id, id),
        value || [],
        baseServiceIds
      );

      const newServiceMetrics = intersectionBetweenServicesIdsAndValues.map(
        (service) => {
          const newService = servicesMetrics.result.find(
            (serviceMetric) => serviceMetric.id === service.id
          );

          return {
            id: service.id,
            metrics: service.metrics.map((metric) => {
              const newMetric = newService?.metrics.find(
                (metricFromService) => metricFromService.id === metric.id
              );

              return {
                criticalHighThreshold: newMetric?.criticalHighThreshold || null,
                criticalLowThreshold: newMetric?.criticalLowThreshold || null,
                id: metric.id,
                name: metric.name,
                unit: metric.unit,
                warningHighThreshold: newMetric?.warningHighThreshold || null,
                warningLowThreshold: newMetric?.warningLowThreshold || null
              };
            }),
            name: service.name
          };
        }
      );

      setFieldValue(
        `data.${propertyName}`,
        isEmpty(newServiceMetrics)
          ? [
              {
                id: '',
                metrics: [],
                name: ''
              }
            ]
          : newServiceMetrics
      );
    },
    useDeepCompare([servicesMetrics, resources])
  );

  useEffect(() => {
    if (hasNoResources() || !singleMetricSection) {
      return;
    }

    setFieldValue(`data.${propertyName}`, [
      {
        id: '',
        metrics: []
      }
    ]);
  }, [hasNoResources()]);

  return {
    addButtonHidden: singleMetricSection,
    addMetric,
    changeMetric,
    changeMetrics,
    changeService,
    deleteMetric,
    deleteMetricItem,
    error: errorToDisplay,
    getMetricOptionDisabled,
    getMetricsFromService,
    getOptionLabel,
    hasNoResources,
    hasOnlyOneService,
    hasReachedTheLimitOfUnits,
    hasTooManyMetrics,
    isLoadingMetrics,
    metricCount,
    resources,
    serviceOptions,
    value: value || []
  };
};
export default useMetrics;
