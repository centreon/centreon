import { ResourceDetails } from '../../Details/models';
import { explainGraphDecoder } from '../api/decoders';
import { aiGraphExplanationEndpoint } from '../api/endpoints';
import { useAiCall } from '../api/useAiCall';
import { ExplainGraphResult } from '../models';
import { maxGraphMetrics, truncate } from '../utils';

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
  const { call, isMutating } = useAiCall<
    ExplainGraphPayload,
    ExplainGraphResult
  >({
    decoder: explainGraphDecoder,
    endpoint: aiGraphExplanationEndpoint
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
    call({
      metrics: metrics.slice(0, maxGraphMetrics),
      name: details.name,
      parent_name: details.parent?.name ?? null,
      performance_data: truncate(details.performance_data),
      resource_type: details.type,
      time_range: timeRange
    });

  return { explain, isMutating };
};

export default useExplainGraph;
