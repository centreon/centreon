import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../../atoms';

interface UseDuplicateProps {
  enableMutation: ({ ids }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useEnable = (): UseDuplicateProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.endpoints?.enable;

  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () => endpoint,
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
