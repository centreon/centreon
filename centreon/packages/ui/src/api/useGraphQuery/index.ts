import { T, always, cond, flatten, isEmpty, join, pipe, pluck } from 'ramda';
import dayjs from 'dayjs';

import { GraphData, useFetchQuery } from '../..';

import { ServiceMetric } from './models';

interface UseMetricsQueryProps {
  metrics: Array<ServiceMetric>;
  baseEndpoint: string;
  timePeriod?: TimePeriod;
}

interface UseMetricsQueryState {
  end: string;
  graphData: GraphData | undefined;
  isGraphLoading: boolean;
  start: string;
}

enum TimePeriod {
  lastDay = 'last_day',
}

const getStartEndFromTimePeriod = (timePeriod: TimePeriod): { start: string; end: string } => {
  return cond([
    [T, always({ start: dayjs().subtract(1, 'day').toISOString(), end: dayjs().toISOString() })]
  ])(timePeriod)
}

const useGraphQuery = ({
  metrics,
  baseEndpoint,
  timePeriod = TimePeriod.lastDay,
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
  } = useFetchQuery<GraphData>({
    getEndpoint: () =>
      `${baseEndpoint}?metricIds=${metricIds}&start=${start}&end=${end}`,
    getQueryKey: () => ['graph', metricIds],
    queryOptions: {
      enabled: !isEmpty(metricIds),
      suspense: false
    }
  });

  const formattedGraphData = graphData
    ? {
        ...graphData,
        global: {
          base: 1000,
          title: ''
        }
      }
    : undefined;

  return {
    end,
    graphData: formattedGraphData,
    isGraphLoading: isFetching || (isLoading && !isEmpty(metricIds)),
    start
  };
};

export default useGraphQuery;
