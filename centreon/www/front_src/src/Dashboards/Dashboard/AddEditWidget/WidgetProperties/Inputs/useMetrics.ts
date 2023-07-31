import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { equals, gt, pluck } from 'ramda';

import {
  ListingModel,
  SelectEntry,
  buildListingEndpoint,
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

import { getDataProperty } from './utils';

interface UseMetricsState {
  addMetric: () => void;
  changeMetric: (index) => (_, newMetrics: Array<SelectEntry>) => void;
  changeService: (index) => (e: ChangeEvent<HTMLInputElement>) => void;
  deleteMetric: (index: number | string) => () => void;
  getMetricsFromService: (serviceId: number) => Array<SelectEntry>;
  hasNoResources: () => boolean;
  hasTooManyMetrics: boolean;
  isLoadingMetrics: boolean;
  metrics: ListingModel<ServiceMetric> | undefined;
  serviceOptions: Array<SelectEntry>;
  value: Array<WidgetDataMetric>;
}

const useMetrics = (propertyName: string): UseMetricsState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const resources = (values.data?.resources || []) as Array<WidgetDataResource>;

  const { data: metrics, isLoading: isLoadingMetrics } = useFetchQuery<
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
      enabled: !!resources.length,
      suspense: false
    }
  });

  const value = useMemo<Array<WidgetDataMetric> | undefined>(
    () => getDataProperty({ obj: values, propertyName }),
    [getDataProperty({ obj: values, propertyName })]
  );

  const hasTooManyMetrics = gt(metrics?.meta?.total || 0, 100);

  const serviceOptions = useMemo<Array<SelectEntry>>(
    () =>
      (metrics?.result || []).map((metric) => ({
        id: metric.serviceId,
        name: metric.resourceName
      })),
    [metrics?.result]
  );

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
        metrics: [],
        serviceId: ''
      }
    ]);
  };

  const deleteMetric = (index: number | string) => (): void => {
    setFieldValue(
      `data.${propertyName}`,
      (value || []).filter((_, i) => !equals(i, index))
    );
  };

  const getMetricsFromService = (serviceId: number): Array<SelectEntry> => {
    return (
      (metrics?.result || []).find((metric) =>
        equals(metric.serviceId, serviceId)
      )?.metrics || []
    );
  };

  const changeService =
    (index) =>
    (e: ChangeEvent<HTMLInputElement>): void => {
      setFieldValue(`data.${propertyName}.${index}.serviceId`, e.target.value);
      setFieldValue(`data.${propertyName}.${index}.metrics`, []);
    };

  const changeMetric =
    (index) =>
    (_, newMetrics: Array<SelectEntry>): void => {
      setFieldValue(`data.${propertyName}.${index}.metrics`, newMetrics);
    };

  console.log(values);

  return {
    addMetric,
    changeMetric,
    changeService,
    deleteMetric,
    getMetricsFromService,
    hasNoResources,
    hasTooManyMetrics,
    isLoadingMetrics,
    metrics,
    serviceOptions,
    value: value || []
  };
};
export default useMetrics;
