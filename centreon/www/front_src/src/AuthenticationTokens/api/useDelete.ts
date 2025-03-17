import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { tokenEndpoint } from './endpoints';

interface UseDeleteProps {
  deleteMutation: ({ userId, name }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDelete = (): UseDeleteProps => {
  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: ({ userId, name }) =>
      tokenEndpoint({ tokenName: name, userId }),
    method: Method.DELETE,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listTokens'] });
    }
  });

  const deleteMutation = ({
    userId,
    name
  }: {
    userId: number;
    name: string;
  }) => {
    return mutateAsync({ _meta: { userId, name } }, {});
  };

  return {
    deleteMutation,
    isMutating
  };
};

export default useDelete;
