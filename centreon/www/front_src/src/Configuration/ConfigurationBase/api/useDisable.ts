import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { configurationAtom } from '../atoms';

interface UseDisableProps {
  disableMutation: ({ ids }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDisable = (): UseDisableProps => {
  const configuration = useAtomValue(configurationAtom);

  const getEndpoint = configuration?.api?.endpoints?.disable;
  const method = configuration?.api?.methods?.disable as Method;

  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint,
    method: method || Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listResources'] });
    }
  });

  const disableMutation = ({
    ids
  }: {
    ids: Array<number>;
  }) => {
    if (equals(method, Method.PATCH)) {
      return mutateAsync({
        _meta: { id: ids[0] },
        payload: { is_activated: false }
      });
    }

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
