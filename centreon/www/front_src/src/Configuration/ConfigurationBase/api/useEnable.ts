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

  const writeBaseEndpoint = configuration?.api?.writeBaseEndpoint;
  const activationField = configuration?.api?.activationField ?? 'is_activated';

  const { isMutating, mutateAsync } = useMutationQuery({
    baseEndpoint: writeBaseEndpoint,
    getEndpoint,
    method: method || Method.POST
  });

  // Not `onSuccess`: that fires once per request, so a fan-out would refetch
  // once per row. Runs after a failure too — a partial one still changed rows.
  const invalidateListing = <T>(result: T): T => {
    queryClient.invalidateQueries({ queryKey: ['listResources'] });

    return result;
  };

  const enableMutation = ({ ids }: { ids: Array<number> }) => {
    if (equals(method, Method.PATCH)) {
      return fanOut(ids, (id) =>
        mutateAsync({ _meta: { id }, payload: { [activationField]: true } })
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
