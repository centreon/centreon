import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';

import { configurationAtom } from '../atoms';
import fanOut from './fanOut';

interface UseMassUpdateState {
  massUpdateMutation: ({
    ids,
    payload
  }: {
    ids: Array<number>;
    payload: object;
  }) => Promise<object | ResponseError>;
}

// The same body sent to every row, through the single-record update.
const useMassUpdate = (): UseMassUpdateState => {
  const configuration = useAtomValue(configurationAtom);

  const getEndpoint = configuration?.api?.endpoints?.update as (
    parameters: Record<string, unknown>
  ) => string;
  const method = configuration?.api?.methods?.update as Method | undefined;

  const queryClient = useQueryClient();

  const { mutateAsync } = useMutationQuery<object, { id: number }>({
    baseEndpoint: configuration?.api?.writeBaseEndpoint,
    getEndpoint,
    method: method || Method.PUT
  });

  // Not `onSuccess`: that fires once per request.
  const invalidateListing = <T>(result: T): T => {
    queryClient.invalidateQueries({ queryKey: ['listResources'] });

    return result;
  };

  const massUpdateMutation = ({
    ids,
    payload
  }: {
    ids: Array<number>;
    payload: object;
  }) =>
    fanOut(ids, (id) => mutateAsync({ _meta: { id }, payload })).then(
      invalidateListing
    );

  return { massUpdateMutation };
};

export default useMassUpdate;
