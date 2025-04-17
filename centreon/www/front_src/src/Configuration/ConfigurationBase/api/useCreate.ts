import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../atoms';

interface UseCreateProps {
  createMutation: (
    payload: Record<string, string | Array<number> | object | null>
  ) => Promise<object | ResponseError>;
}

const useCreate = (): UseCreateProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.create as string;

  const queryClient = useQueryClient();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => endpoint,
    method: Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listResources'] });
      queryClient.resetQueries({ queryKey: ['getDetails'] });
    }
  });
  const createMutation = (payload) => {
    return mutateAsync({
      payload
    });
  };

  return {
    createMutation
  };
};

export default useCreate;
