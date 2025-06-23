import { useQueryClient } from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../atoms';

interface UseEnableProps {
  enableMutation: ({
    ids
  }: { ids: Array<number> }) => Promise<object | ResponseError>;
  isMutating: boolean;
}

const useEnable = (): UseEnableProps => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.enable as string;

  const queryClient = useQueryClient();

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () => endpoint,
    method: Method.POST,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['listResources'] });
    }
  });

  const enableMutation = ({
    ids
  }: {
    ids: Array<number>;
  }) => {
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
