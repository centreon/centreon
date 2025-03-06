import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../atoms';

interface UseDeleteProps {
  deleteMutation: ({ ids }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDelete = (): UseDeleteProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.delete;

  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () => endpoint,
    method: Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const deleteMutation = ({
    ids
  }: {
    ids: Array<number>;
  }) => {
    return mutateAsync({
      payload: { ids }
    });
  };

  return {
    deleteMutation,
    isMutating
  };
};

export default useDelete;
