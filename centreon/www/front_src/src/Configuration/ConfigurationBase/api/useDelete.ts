import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';

import { configurationAtom } from '../atoms';

interface UseDeleteProps {
  deleteMutation: ({
    ids
  }: {
    ids: Array<number>;
  }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDelete = (): UseDeleteProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.delete as string;

  const queryClient = useQueryClient();

  const writeBaseEndpoint = configuration?.api?.writeBaseEndpoint;

  const { isMutating, mutateAsync } = useMutationQuery({
    baseEndpoint: writeBaseEndpoint,
    getEndpoint: () => endpoint,
    method: Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listResources'] });
    }
  });

  const deleteMutation = ({ ids }: { ids: Array<number> }) => {
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
