import { T, always, cond, flatten, isEmpty, join, pipe, pluck } from 'ramda';
import dayjs from 'dayjs';

import { LineChartData, useFetchQuery } from '../..';

import { ServiceMetric } from './models';

interface UseMetricsQueryProps {
  baseEndpoint: string;
  metrics: Array<ServiceMetric>;
  timePeriod?: TimePeriod;
}

interface UseMetricsQueryState {
  end: string;
  graphData: LineChartData | undefined;
  isGraphLoading: boolean;
  isMetricIdsEmpty: boolean;
  start: string;
}

enum TimePeriod {
  lastDay = 'last_day'
}

const getStartEndFromTimePeriod = (
  timePeriod: TimePeriod
): { end: string; start: string } => {
  return cond([
    [
      T,
      always({
        end: dayjs().toISOString(),
        start: dayjs().subtract(1, 'day').toISOString()
      })
    ]
  ])(timePeriod);
};

interface PerformanceGraphData extends Omit<LineChartData, 'global'> {
  base: number;
}

const useGraphQuery = ({
  metrics,
  baseEndpoint,
  timePeriod = TimePeriod.lastDay
}: UseMetricsQueryProps): UseMetricsQueryState => {
  const metricIds = pipe(
    pluck('metrics'),
    flatten,
    pluck('id'),
    join(',')
  )(metrics);

  const { end, start } = getStartEndFromTimePeriod(timePeriod);

  const {
    data: graphData,
    isFetching,
    isLoading
  } = useFetchQuery<PerformanceGraphData>({
    getEndpoint: () =>
      `${baseEndpoint}?metricIds=[${metricIds}]&start=${start}&end=${end}`,
    getQueryKey: () => ['graph', metricIds],
    queryOptions: {
      enabled: !isEmpty(metricIds),
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

  return {
    end,
    graphData: formattedGraphData,
    isGraphLoading: isFetching || (isLoading && !isEmpty(metricIds)),
    isMetricIdsEmpty: isEmpty(metricIds),
    start
  };
};

export default useGraphQuery;
