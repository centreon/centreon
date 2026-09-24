// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { configurationAtom } from '../atoms';
import fanOut from './fanOut';

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
    method: method || Method.POST
  });

  // Not `onSuccess`: that fires once per request, so a fan-out would refetch
  // once per row. Runs after a failure too — a partial one still changed rows.
  const invalidateListing = <T>(result: T): T => {
    queryClient.invalidateQueries({ queryKey: ['listResources'] });

    return result;
  };

  const disableMutation = ({ ids }: { ids: Array<number> }) => {
    if (equals(method, Method.PATCH)) {
      return fanOut(ids, (id) =>
        mutateAsync({ _meta: { id }, payload: { is_activated: false } })
      ).then(invalidateListing);
    }

    return mutateAsync({
      payload: { ids }
    }).then(invalidateListing);
  };

  return {
    disableMutation,
    isMutating
  };
};

export default useDisable;
