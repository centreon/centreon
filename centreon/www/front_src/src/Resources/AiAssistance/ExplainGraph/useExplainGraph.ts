import { Method, useMutationQuery } from '@centreon/ui';

import { ResourceDetails } from '../../Details/models';
import { aiGraphExplanationEndpoint } from '../endpoints';
import { ExplainGraphResult } from '../models';

export interface GraphMetricSummary {
  average: number | null;
  current: number | null;
  maximum: number | null;
  minimum: number | null;
  name: string;
  unit: string | null;
}

interface ExplainGraphPayload {
  metrics: Array<GraphMetricSummary>;
  name: string;
  parent_name: string | null;
  performance_data: string | null;
  resource_type: string;
  time_range: string | null;
}

interface UseExplainGraphState {
  explain: (args: {
    details: ResourceDetails;
    metrics: Array<GraphMetricSummary>;
    timeRange: string | null;
  }) => Promise<ExplainGraphResult | null>;
  isMutating: boolean;
}

const useExplainGraph = (): UseExplainGraphState => {
  const { mutateAsync, isMutating } = useMutationQuery<
    ExplainGraphPayload,
    undefined
  >({
    getEndpoint: () => aiGraphExplanationEndpoint,
    method: Method.POST
  });

  const explain = ({
    details,
    metrics,
    timeRange
  }: {
    details: ResourceDetails;
    metrics: Array<GraphMetricSummary>;
    timeRange: string | null;
  }): Promise<ExplainGraphResult | null> =>
    mutateAsync({
      payload: {
        metrics,
        name: details.name,
        parent_name: details.parent?.name ?? null,
        performance_data: details.performance_data ?? null,
        resource_type: details.type,
        time_range: timeRange
      }
    }).then((result) => {
      if ('isError' in result) {
        return null;
      }

      return result as unknown as ExplainGraphResult;
    });

  return { explain, isMutating };
};

export default useExplainGraph;
