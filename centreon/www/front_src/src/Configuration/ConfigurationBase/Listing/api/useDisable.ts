import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../../atoms';

interface UseDuplicateProps {
  disableMutation: ({ ids }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDisable = (): UseDuplicateProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.endpoints?.disable;

  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () => endpoint,
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
