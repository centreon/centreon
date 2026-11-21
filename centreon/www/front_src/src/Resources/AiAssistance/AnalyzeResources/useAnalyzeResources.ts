import { Resource } from '../../models';
import { correlateAlertsDecoder } from '../api/decoders';
import { aiAlertCorrelationEndpoint } from '../api/endpoints';
import { useAiCall } from '../api/useAiCall';
import { CorrelateAlertsResult } from '../models';

interface ResourcePayload {
  duration: string | null;
  information: string | null;
  is_acknowledged: boolean;
  is_in_downtime: boolean;
  name: string;
  parent_name: string | null;
  resource_type: string;
  status: string;
  tries: string | null;
}

interface AnalyzePayload {
  resources: Array<ResourcePayload>;
}

interface UseAnalyzeResourcesState {
  analyze: (
    resources: Array<Resource>
  ) => Promise<CorrelateAlertsResult | null>;
  isMutating: boolean;
}

const useAnalyzeResources = (): UseAnalyzeResourcesState => {
  const { call, isMutating } = useAiCall<AnalyzePayload, CorrelateAlertsResult>(
    {
      decoder: correlateAlertsDecoder,
      endpoint: aiAlertCorrelationEndpoint
    }
  );

  const analyze = (
    resources: Array<Resource>
  ): Promise<CorrelateAlertsResult | null> =>
    call({
      resources: resources.map((resource) => ({
        duration: resource.duration ?? null,
        information: resource.information ?? null,
        is_acknowledged: resource.is_acknowledged ?? false,
        is_in_downtime: resource.is_in_downtime ?? false,
        name: resource.name,
        parent_name: resource.parent?.name ?? null,
        resource_type: resource.type,
        status: resource.status?.name ?? 'UNKNOWN',
        tries: resource.tries ?? null
      }))
    });

  return { analyze, isMutating };
};

export default useAnalyzeResources;
