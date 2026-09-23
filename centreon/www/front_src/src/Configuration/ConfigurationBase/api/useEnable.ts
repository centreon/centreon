// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { equals, propEq } from 'ramda';

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
    // PATCH is a single-resource operation, so a selection has to fan out.
    if (equals(method, Method.PATCH)) {
      return Promise.all(
        ids.map((id) =>
          mutateAsync({
            _meta: { id },
            payload: { is_activated: true }
          })
        )
      ).then((responses) => responses.find(propEq(true, 'isError')) ?? {});
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
