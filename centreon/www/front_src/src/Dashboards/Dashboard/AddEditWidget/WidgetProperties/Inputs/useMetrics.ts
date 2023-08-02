import { ChangeEvent, useEffect, useMemo } from 'react';

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
  pipe,
  pluck,
  uniq
} from 'ramda';

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
} from '../../models';
import { metricsEndpoint } from '../../api/endpoints';
import { serviceMetricsDecoder } from '../../api/decoders';
import { labelPleaseSelectAMetric } from '../../../translatedLabels';

import { getDataProperty } from './utils';

interface UseMetricsState {
  addMetric: () => void;
  changeMetric: (index) => (_, newMetrics: Array<SelectEntry> | null) => void;
  changeService: (index) => (e: ChangeEvent<HTMLInputElement>) => void;
  deleteMetric: (index: number | string) => () => void;
  error: string | null;
  getMetricOptionDisabled: (metricOption) => boolean;
  getMetricsFromService: (serviceId: number) => Array<SelectEntry>;
  getOptionLabel: (metric) => string;
  hasNoResources: () => boolean;
  hasReachedTheLimitOfUnits: boolean;
  hasTooManyMetrics: boolean;
  isLoadingMetrics: boolean;
  metricCount: number | undefined;
  serviceOptions: Array<SelectEntry>;
  value: Array<WidgetDataMetric>;
}

const useMetrics = (propertyName: string): UseMetricsState => {
  const { values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<Widget>();

  const resources = (values.data?.resources || []) as Array<WidgetDataResource>;

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
              field: resource.resourceType,
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
        metrics: []
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
      setFieldValue(`data.${propertyName}.${index}.id`, e.target.value);
      setFieldValue(`data.${propertyName}.${index}.metrics`, []);
    };

  const changeMetric =
    (index) =>
    (_, newMetrics: Array<SelectEntry> | null): void => {
      setFieldValue(`data.${propertyName}.${index}.metrics`, newMetrics || []);
      setFieldTouched(`data.${propertyName}`, true, false);
    };

  useEffect(() => {
    if (isNil(servicesMetrics)) {
      return;
    }

    if (isEmpty(resources)) {
      setFieldValue(`data.${propertyName}`, []);

      return;
    }

    const baseServiceIds = pluck('id', servicesMetrics?.result || []);

    const intersectionBetweenServicesIdsAndValues = innerJoin(
      (service, id) => equals(service.id, id),
      value || [],
      baseServiceIds
    );

    setFieldValue(
      `data.${propertyName}`,
      intersectionBetweenServicesIdsAndValues
    );
  }, useDeepCompare([servicesMetrics, resources]));

  return {
    addMetric,
    changeMetric,
    changeService,
    deleteMetric,
    error: errorToDisplay,
    getMetricOptionDisabled,
    getMetricsFromService,
    getOptionLabel,
    hasNoResources,
    hasReachedTheLimitOfUnits,
    hasTooManyMetrics,
    isLoadingMetrics,
    metricCount,
    serviceOptions,
    value: value || []
  };
};
export default useMetrics;
