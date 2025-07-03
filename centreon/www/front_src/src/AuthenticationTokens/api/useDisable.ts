import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { getTokenEndpoint } from './endpoints';

interface UseDisableProps {
  disableMutation: ({ userId, name }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDisable = (): UseDisableProps => {
  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: ({ userId, name }) =>
      getTokenEndpoint({ tokenName: name, userId }),
    method: Method.PATCH,
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['listTokens'] });
    }
  });

  const disableMutation = ({
    userId,
    name
  }: {
    userId: number;
    name: string;
  }) => {
    return mutateAsync(
      { _meta: { userId, name }, payload: { is_revoked: true } },
      {}
    );
  };

  return {
    disableMutation,
    isMutating
  };
};

export default useDisable;
