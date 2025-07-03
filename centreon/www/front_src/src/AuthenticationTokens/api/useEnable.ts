import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { getTokenEndpoint } from './endpoints';

interface UseEnableeProps {
  enableMutation: ({ userId, name }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useEnable = (): UseEnableeProps => {
  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: ({ userId, name }) =>
      getTokenEndpoint({ tokenName: name, userId }),
    method: Method.PATCH,
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['listTokens'] });
    }
  });

  const enableMutation = ({
    userId,
    name
  }: {
    userId: number;
    name: string;
  }) => {
    return mutateAsync(
      { _meta: { userId, name }, payload: { is_revoked: false } },
      {}
    );
  };

  return {
    enableMutation,
    isMutating
  };
};

export default useEnable;
