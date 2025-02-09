import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { bulkEnableHostGroupEndpoint } from '../../api/endpoints';

interface UseDuplicateProps {
  enableMutation: ({ ids }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useEnable = (): UseDuplicateProps => {
  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () => bulkEnableHostGroupEndpoint,
    method: Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const enableMutation = ({
    ids
  }: {
    ids: Array<number>;
  }) => {
    return mutateAsync({
      payload: { ids }
    });
  };

  return {
    enableMutation,
    isMutating
  };
};

export default useEnable;
