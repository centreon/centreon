import { equals, flatten, has, isEmpty, join, pipe, pluck } from 'ramda';
import dayjs from 'dayjs';

import { LineChartData, useFetchQuery } from '../..';

import { ServiceMetric } from './models';

interface CustomTimePeriod {
  end: string;
  start: string;
}

interface UseMetricsQueryProps {
  baseEndpoint: string;
  metrics: Array<ServiceMetric>;
  refreshInterval?: number | false;
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
  isMetricIdsEmpty: boolean;
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

const useGraphQuery = ({
  metrics,
  baseEndpoint,
  timePeriod = {
    timePeriodType: 1
  },
  refreshInterval = false
}: UseMetricsQueryProps): UseMetricsQueryState => {
  const metricIds = pipe(
    pluck('metrics'),
    flatten,
    pluck('id'),
    join(',')
  )(metrics);

  const timePeriodToUse = equals(timePeriod?.timePeriodType, -1)
    ? {
        end: timePeriod.end,
        start: timePeriod.start
      }
    : timePeriod?.timePeriodType;

  const {
    data: graphData,
    isFetching,
    isLoading
  } = useFetchQuery<PerformanceGraphData>({
    getEndpoint: () => {
      if (isCustomTimePeriod(timePeriodToUse)) {
        return `${baseEndpoint}?metricIds=[${metricIds}]&start=${
          (timePeriodToUse as CustomTimePeriod).start
        }&end=${(timePeriodToUse as CustomTimePeriod).end}`;
      }

      const { end, start } = getStartEndFromTimePeriod(
        timePeriodToUse as number
      );

      return `${baseEndpoint}?metricIds=[${metricIds}]&start=${start}&end=${end}`;
    },
    getQueryKey: () => ['graph', metricIds, timePeriod],
    queryOptions: {
      enabled: !isEmpty(metricIds),
      refetchInterval: refreshInterval,
      suspense: false
    }
  });

  const formattedGraphData = graphData
    ? {
        global: {
          base: graphData.base,
          title: ''
        },
        metrics: graphData.metrics,
        times: graphData.times
      }
    : undefined;

  const { end, start } = isCustomTimePeriod(timePeriodToUse)
    ? (timePeriodToUse as CustomTimePeriod)
    : getStartEndFromTimePeriod(timePeriodToUse as number);

  return {
    end,
    graphData: formattedGraphData,
    isGraphLoading: isFetching || (isLoading && !isEmpty(metricIds)),
    isMetricIdsEmpty: isEmpty(metricIds),
    start
  };
};

export default useGraphQuery;
