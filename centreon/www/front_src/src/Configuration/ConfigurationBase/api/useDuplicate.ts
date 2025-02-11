import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../atoms';

interface UseDuplicateProps {
  duplicateMutation: ({ ids, nbDuplicates }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDuplicate = (): UseDuplicateProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.duplicate;

  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () => endpoint,
    method: Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const duplicateMutation = ({
    ids,
    nbDuplicates
  }: {
    ids: Array<number>;
    nbDuplicates: number;
  }) => {
    return mutateAsync({
      payload: { ids, nb_duplicates: nbDuplicates }
    });
  };

  return {
    duplicateMutation,
    isMutating
  };
};

export default useDuplicate;
