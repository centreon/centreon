import { useRef } from 'react';

import dayjs from 'dayjs';
import {
  equals,
  flatten,
  has,
  includes,
  isEmpty,
  not,
  pipe,
  pluck
} from 'ramda';

import { LineChartData, buildListingEndpoint, useFetchQuery } from '../..';

import { Metric, Resource, WidgetResourceType } from './models';

interface CustomTimePeriod {
  end: string;
  start: string;
}

interface UseMetricsQueryProps {
  baseEndpoint: string;
  bypassMetricsExclusion?: boolean;
  bypassQueryParams?: boolean;
  includeAllResources?: boolean;
  metrics: Array<Metric>;
  prefix?: string;
  refreshCount?: number;
  refreshInterval?: number | false;
  resources?: Array<Resource>;
  timePeriod?: {
    end?: string | null;
    start?: string | null;
    timePeriodType: number;
  };
}

interface UseMetricsQueryState {
  end: string;
  graphData: LineChartData | undefined;
  isGraphLoading: boolean;
  isMetricsEmpty: boolean;
  start: string;
}

const getStartEndFromTimePeriod = (
  timePeriod: number
): { end: string; start: string } => {
  return {
    end: dayjs().toISOString(),
    start: dayjs().subtract(timePeriod, 'hour').toISOString()
  };
};

const isCustomTimePeriod = (
  timePeriod:
    | number
    | {
        end?: string | null;
        start?: string | null;
      }
): boolean => has('end', timePeriod) && has('start', timePeriod);

interface PerformanceGraphData extends Omit<LineChartData, 'global'> {
  base: number;
}

export const resourceTypeQueryParameter = {
  [WidgetResourceType.host]: 'host.id',
  [WidgetResourceType.hostCategory]: 'hostcategory.id',
  [WidgetResourceType.hostGroup]: 'hostgroup.id',
  [WidgetResourceType.serviceCategory]: 'servicecategory.id',
  [WidgetResourceType.serviceGroup]: 'servicegroup.id',
  [WidgetResourceType.service]: 'service.name',
  [WidgetResourceType.metaService]: 'metaservice.id'
};

const areResourcesFullfilled = (value: Array<Resource>): boolean =>
  value?.every(
    ({ resourceType, resources }) =>
      !isEmpty(resourceType) && !isEmpty(resources)
  );

const useGraphQuery = ({
  bypassMetricsExclusion,
  metrics,
  resources = [],
  baseEndpoint,
  timePeriod = {
    timePeriodType: 1
  },
  refreshInterval = false,
  refreshCount,
  bypassQueryParams = false,
  prefix
}: UseMetricsQueryProps): UseMetricsQueryState => {
  const isMetaServiceSelected =
    equals(resources.length, 1) &&
    equals(resources[0].resourceType, WidgetResourceType.metaService);

  const timePeriodToUse = equals(timePeriod?.timePeriodType, -1)
    ? {
        end: timePeriod.end,
        start: timePeriod.start
      }
    : timePeriod?.timePeriodType;

  const startAndEnd = isCustomTimePeriod(timePeriodToUse)
    ? (timePeriodToUse as CustomTimePeriod)
    : getStartEndFromTimePeriod(timePeriodToUse as number);

  const definedMetrics = metrics.filter((metric) => metric);
  const formattedDefinedMetrics = isMetaServiceSelected
    ? 'metric_names[]=value'
    : definedMetrics
        .map((metric) => `metric_names[]=${encodeURIComponent(metric.name)}`)
        .join('&');

  const prefixQuery = prefix ? [prefix] : [];

  const {
    data: graphData,
    isFetching,
    isLoading
  } = useFetchQuery<PerformanceGraphData>({
    getEndpoint: () => {
      if (bypassQueryParams) {
        return baseEndpoint;
      }

      const endpoint = buildListingEndpoint({
        baseEndpoint,
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
      });

      return `${endpoint}&start=${startAndEnd.start}&end=${startAndEnd.end}&${formattedDefinedMetrics}`;
    },
    getQueryKey: () => [
      ...prefixQuery,
      'graph',
      JSON.stringify(definedMetrics),
      JSON.stringify(resources),
      timePeriod,
      refreshCount || 0
    ],
    queryOptions: {
      enabled:
        areResourcesFullfilled(resources) &&
        (isMetaServiceSelected || !isEmpty(definedMetrics)),
      refetchInterval: refreshInterval,
      suspense: false
    },
    useLongCache: true
  });

  const data = useRef<PerformanceGraphData | undefined>(undefined);
  if (graphData) {
    data.current = graphData;
  }

  const formattedGraphData = data.current
    ? {
        global: {
          base: data.current.base,
          title: ''
        },
        metrics: bypassMetricsExclusion
          ? data.current.metrics
          : data.current.metrics.filter(({ metric_id }) => {
              return pipe(
                pluck('excludedMetrics'),
                flatten,
                includes(metric_id),
                not
              )(metrics);
            }),
        times: data.current.times
      }
    : undefined;

  const { end, start } = isCustomTimePeriod(timePeriodToUse)
    ? (timePeriodToUse as CustomTimePeriod)
    : getStartEndFromTimePeriod(timePeriodToUse as number);

  return {
    end,
    graphData: formattedGraphData,
    isGraphLoading: isFetching || (isLoading && !isEmpty(definedMetrics)),
    isMetricsEmpty: isEmpty(definedMetrics),
    start
  };
};

export default useGraphQuery;
