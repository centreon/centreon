import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { configurationAtom } from '../atoms';

interface UseEnableProps {
  enableMutation: ({
    ids
  }: {
    ids: Array<number>;
  }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useEnable = (): UseEnableProps => {
  const configuration = useAtomValue(configurationAtom);

  const getEndpoint = configuration?.api?.endpoints?.enable as string;
  const method = configuration?.api?.methods?.enable as Method;

  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint,
    method: method || Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listResources'] });
    }
  });

  const enableMutation = ({ ids }: { ids: Array<number> }) => {
    if (equals(method, Method.PATCH)) {
      return mutateAsync({
        _meta: { id: ids[0] },
        payload: { is_activated: true }
      });
    }

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
