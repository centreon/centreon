import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../atoms';

interface UseDisableProps {
  disableMutation: ({ ids }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDisable = (): UseDisableProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.disable;

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
