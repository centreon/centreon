// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { equals, propEq } from 'ramda';

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

  const disableMutation = ({ ids }: { ids: Array<number> }) => {
    // PATCH is a single-resource operation, so a selection has to fan out.
    if (equals(method, Method.PATCH)) {
      return Promise.all(
        ids.map((id) =>
          mutateAsync({
            _meta: { id },
            payload: { is_activated: false }
          })
        )
      ).then((responses) => responses.find(propEq(true, 'isError')) ?? {});
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
