import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { is } from 'ramda';

import { configurationAtom } from '../atoms';
import fanOut from './fanOut';

interface UseDuplicateProps {
  duplicateMutation: ({
    ids,
    nbDuplicates
  }: {
    ids: Array<number>;
    nbDuplicates?: number;
  }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDuplicate = (): UseDuplicateProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.duplicate;
  const isSingleDuplicate = configuration?.api?.isSingleDuplicate;

  // A function means the route names one resource, so a selection fans out.
  const duplicatesEachByItself = is(Function, endpoint);

  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: duplicatesEachByItself
      ? (endpoint as ({ id }: { id: number }) => string)
      : () => endpoint as string,
    method: Method.POST
  });

  const invalidateListing = <T>(result: T): T => {
    queryClient.invalidateQueries({ queryKey: ['listResources'] });

    return result;
  };

  const duplicateMutation = ({
    ids,
    nbDuplicates
  }: {
    ids: Array<number>;
    nbDuplicates?: number;
  }) => {
    if (duplicatesEachByItself) {
      return fanOut(ids, (id) => mutateAsync({ _meta: { id } })).then(
        invalidateListing
      );
    }

    return mutateAsync({
      payload: isSingleDuplicate
        ? { ids }
        : { ids, nb_duplicates: nbDuplicates }
    }).then(invalidateListing);
  };

  return {
    duplicateMutation,
    isMutating
  };
};

export default useDuplicate;
