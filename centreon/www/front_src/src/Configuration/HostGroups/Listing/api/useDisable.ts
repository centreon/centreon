import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { bulkDisableHostGroupEndpoint } from '../../api/endpoints';

interface UseDuplicateProps {
  disableMutation: ({ ids }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDisable = (): UseDuplicateProps => {
  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () => bulkDisableHostGroupEndpoint,
    method: Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const disableMutation = ({
    ids
  }: {
    ids: Array<number>;
  }) => {
    return mutateAsync({
      payload: { ids }
    });
  };

  return {
    disableMutation,
    isMutating
  };
};

export default useDisable;
