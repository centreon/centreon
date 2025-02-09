import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { getHostGroupEndpoint } from '../../api/endpoints';

interface UseDeleteProps {
  deleteOneMutation: ({ id }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDelete = (): UseDeleteProps => {
  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: ({ id }) => getHostGroupEndpoint({ id }),
    method: Method.DELETE,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const deleteOneMutation = ({
    id
  }: {
    id: number;
  }) => {
    return mutateAsync({ _meta: { id } }, {});
  };

  return {
    deleteOneMutation,
    isMutating
  };
};

export default useDelete;
