import { Method, useMutationQuery } from '@centreon/ui';

import { ResourceDetails } from '../../Details/models';
import { aiResourceExplanationEndpoint } from '../endpoints';
import { ExplainResourceResult } from '../models';

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
  const { mutateAsync, isMutating } = useMutationQuery<
    ExplainResourcePayload,
    undefined
  >({
    getEndpoint: () => aiResourceExplanationEndpoint,
    method: Method.POST
  });

  const explain = (
    details: ResourceDetails
  ): Promise<ExplainResourceResult | null> =>
    mutateAsync({
      payload: {
        command_line: details.command_line ?? null,
        duration: details.duration ?? null,
        information: details.information ?? null,
        name: details.name,
        parent_name: details.parent?.name ?? null,
        performance_data: details.performance_data ?? null,
        resource_type: details.type,
        status: details.status?.name ?? 'UNKNOWN',
        tries: details.tries ?? null
      }
    }).then((result) => {
      if ('isError' in result) {
        return null;
      }

      return result as unknown as ExplainResourceResult;
    });

  return { explain, isMutating };
};

export default useExplainResource;
