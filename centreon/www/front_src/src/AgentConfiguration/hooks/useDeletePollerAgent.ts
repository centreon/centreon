import { Method, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';

import {
  type GetPollerAgentEndpointProps,
  getPollerAgentEndpoint
} from '../api/endpoints';

interface UseDeletePollerAgent {
  isMutating: boolean;
  deleteItem: ({
    pollerId,
    agentId
  }: {
    pollerId?: number;
    agentId: number;
  }) => Promise<void>;
}

export const useDeletePollerAgent = (): UseDeletePollerAgent => {
  const queryClient = useQueryClient();

  const { mutateAsync, isMutating } = useMutationQuery<
    object,
    GetPollerAgentEndpointProps
  >({
    getEndpoint: (_meta) =>
      getPollerAgentEndpoint(_meta as GetPollerAgentEndpointProps),
    method: Method.DELETE,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listAgentConfigurations'] });
    }
  });

  const deleteItem = ({
    pollerId,
    agentId
  }: {
    pollerId?: number;
    agentId: number;
  }): Promise<void> =>
    mutateAsync({ _meta: { agentId, pollerId } }) as unknown as Promise<void>;

  return {
    deleteItem,
    isMutating
  };
};
