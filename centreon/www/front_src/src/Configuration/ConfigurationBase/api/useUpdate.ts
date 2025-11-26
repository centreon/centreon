import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../atoms';

interface UseUpdateProps {
  updateMutation: (
    id: number
  ) => (
    payload: Record<string, string | Array<number> | object | null>
  ) => Promise<object | ResponseError>;
}

const useUpdate = (): UseUpdateProps => {
  const configuration = useAtomValue(configurationAtom);

  const getEndpoint = configuration?.api?.endpoints?.update;
  const method = configuration?.api?.methods?.update as Method;

  const queryClient = useQueryClient();

  const { mutateAsync } = useMutationQuery({
    getEndpoint,
    method: method || Method.PUT,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listResources'] });
    }
  });
  const updateMutation = (id: number) => (payload) => {
    return mutateAsync({ _meta: { id }, payload });
  };

  return {
    updateMutation
  };
};

export default useUpdate;
