// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { configurationAtom } from '../atoms';
import fanOut from './fanOut';

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
    method: method || Method.POST
  });

  // Not `onSuccess`: that fires once per request, so a fan-out refetched the
  // listing once per selected row. `customFetch` resolves rather than rejects,
  // so this also runs after a failure — a partial one still changed rows.
  const invalidateListing = <T>(result: T): T => {
    queryClient.invalidateQueries({ queryKey: ['listResources'] });

    return result;
  };

  const enableMutation = ({ ids }: { ids: Array<number> }) => {
    if (equals(method, Method.PATCH)) {
      return fanOut(ids, (id) =>
        mutateAsync({ _meta: { id }, payload: { is_activated: true } })
      ).then(invalidateListing);
    }

    return mutateAsync({
      payload: { ids }
    }).then(invalidateListing);
  };

  return {
    enableMutation,
    isMutating
  };
};

export default useEnable;
