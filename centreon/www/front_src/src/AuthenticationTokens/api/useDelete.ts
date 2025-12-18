import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';

import { getTokenEndpoint } from './endpoints';

interface UseDeleteProps {
  deleteMutation: ({ userId, name }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDelete = (): UseDeleteProps => {
  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: ({ userId, name }) =>
      getTokenEndpoint({ tokenName: name, userId }),
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
    return mutateAsync({ _meta: { name, userId } }, {});
  };

  return {
    deleteMutation,
    isMutating
  };
};

export default useDelete;
