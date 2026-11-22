import { ResourceDetails } from '../../Details/models';
import { explainResourceDecoder } from '../api/decoders';
import { aiResourceExplanationEndpoint } from '../api/endpoints';
import { useAiCall } from '../api/useAiCall';
import { ExplainResourceResult } from '../models';
import { truncate } from '../utils';

interface ExplainResourcePayload {
  command_line: string | null;
  duration: string | null;
  information: string | null;
  name: string;
  parent_name: string | null;
  performance_data: string | null;
  resource_type: string;
  status: string;
  tries: string | null;
}

interface UseExplainResourceState {
  explain: (details: ResourceDetails) => Promise<ExplainResourceResult | null>;
  isMutating: boolean;
}

const useExplainResource = (): UseExplainResourceState => {
  const { call, isMutating } = useAiCall<
    ExplainResourcePayload,
    ExplainResourceResult
  >({
    decoder: explainResourceDecoder,
    endpoint: aiResourceExplanationEndpoint
  });

  const explain = (
    details: ResourceDetails
  ): Promise<ExplainResourceResult | null> =>
    call({
      command_line: truncate(details.command_line),
      duration: details.duration ?? null,
      information: truncate(details.information),
      name: details.name,
      parent_name: details.parent?.name ?? null,
      performance_data: truncate(details.performance_data),
      resource_type: details.type,
      status: details.status?.name ?? 'UNKNOWN',
      tries: details.tries ?? null
    });

  return { explain, isMutating };
};

export default useExplainResource;
