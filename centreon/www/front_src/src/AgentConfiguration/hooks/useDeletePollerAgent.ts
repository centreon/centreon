import { Method, useMutationQuery } from '@centreon/ui';
import { useQueryClient } from '@tanstack/react-query';
import { getPollerAgentEndpoint } from '../api/endpoints';

interface UseDeletePollerAgent {
  isMutating: boolean;
  deleteItem: ({ pollerId, agentId }) => Promise<void>;
}

export const useDeletePollerAgent = (): UseDeletePollerAgent => {
  const queryClient = useQueryClient();

  const { mutateAsync, isMutating } = useMutationQuery({
    getEndpoint: (_meta) => getPollerAgentEndpoint(_meta),
    method: Method.DELETE,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['agent-configurations'] });
    }
  });

  const deleteItem = ({ pollerId, agentId }): Promise<void> =>
    mutateAsync({ _meta: { pollerId, agentId } });

  return {
    isMutating,
    deleteItem
  };
};
