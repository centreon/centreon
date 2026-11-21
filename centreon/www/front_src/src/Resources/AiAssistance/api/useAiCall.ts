import { Method, useMutationQuery } from '@centreon/ui';

import type { JsonDecoder } from 'ts.data.json';

interface Props<TResult> {
  decoder: JsonDecoder.Decoder<TResult>;
  endpoint: string;
}

interface AiCallState<TPayload, TResult> {
  call: (payload: TPayload) => Promise<TResult | null>;
  isMutating: boolean;
}

// A POST to one of the AI assistance endpoints. No status bypasses the error
// snackbar: a failed call (409, 502 or any other status) shows the API's
// message there and resolves to null.
export const useAiCall = <TPayload extends object, TResult extends object>({
  decoder,
  endpoint
}: Props<TResult>): AiCallState<TPayload, TResult> => {
  const { mutateAsync, isMutating } = useMutationQuery<TResult, undefined>({
    decoder,
    getEndpoint: () => endpoint,
    method: Method.POST
  });

  const call = async (payload: TPayload): Promise<TResult | null> => {
    // the hook types the request body with the response type
    const response = await mutateAsync({
      payload: payload as unknown as TResult
    });

    if ('isError' in response) {
      return null;
    }

    return response;
  };

  return { call, isMutating };
};
