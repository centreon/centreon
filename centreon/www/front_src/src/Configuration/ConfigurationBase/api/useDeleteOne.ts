// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';

import { configurationAtom } from '../atoms';
import fanOut from './fanOut';

interface UseDeleteOneProps {
  deleteOneMutation: ({ id }) => Promise<object | ResponseError>;
  deleteEachMutation: ({ ids }) => Promise<object>;
  isMutating: boolean;
}

const useDeleteOne = (): UseDeleteOneProps => {
  const queryClient = useQueryClient();

  const configuration = useAtomValue(configurationAtom);
  const getEndpoint = configuration?.api?.endpoints?.deleteOne;

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint,
    method: Method.DELETE
  });

  // Not `onSuccess`: that fires once per request, so a fan-out would refetch
  // once per row. Runs after a failure too — a partial one still changed rows.
  const invalidateListing = <T>(result: T): T => {
    queryClient.invalidateQueries({ queryKey: ['listResources'] });

    return result;
  };

  const deleteOneMutation = ({ id }: { id: number }) =>
    mutateAsync({ _meta: { id } }, {}).then(invalidateListing);

  const deleteEachMutation = ({ ids }: { ids: Array<number> }) =>
    fanOut(ids, (id) => mutateAsync({ _meta: { id } }, {})).then(
      invalidateListing
    );

  return {
    deleteEachMutation,
    deleteOneMutation,
    isMutating
  };
};

export default useDeleteOne;
