import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { bulkDuplicateHostGroupEndpoint } from '../../api/endpoints';

interface UseDuplicateProps {
  duplicateMutation: ({ ids, nbDuplicates }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useDuplicate = (): UseDuplicateProps => {
  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () => bulkDuplicateHostGroupEndpoint,
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
