import { flatten, isEmpty, join, pipe, pluck } from 'ramda';
import dayjs from 'dayjs';

import { LineChartData, useFetchQuery } from '../..';

import { ServiceMetric } from './models';

interface UseMetricsQueryProps {
  baseEndpoint: string;
  metrics: Array<ServiceMetric>;
  refreshInterval?: number | false;
  timePeriod?: number;
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

interface PerformanceGraphData extends Omit<LineChartData, 'global'> {
  base: number;
}

const useGraphQuery = ({
  metrics,
  baseEndpoint,
  timePeriod = 1,
  refreshInterval = false
}: UseMetricsQueryProps): UseMetricsQueryState => {
  const metricIds = pipe(
    pluck('metrics'),
    flatten,
    pluck('id'),
    join(',')
  )(metrics);

  const {
    data: graphData,
    isFetching,
    isLoading
  } = useFetchQuery<PerformanceGraphData>({
    getEndpoint: () => {
      const { end, start } = getStartEndFromTimePeriod(timePeriod);

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

  const { end, start } = getStartEndFromTimePeriod(timePeriod);

  return {
    end,
    graphData: formattedGraphData,
    isGraphLoading: isFetching || (isLoading && !isEmpty(metricIds)),
    isMetricIdsEmpty: isEmpty(metricIds),
    start
  };
};

export default useGraphQuery;
