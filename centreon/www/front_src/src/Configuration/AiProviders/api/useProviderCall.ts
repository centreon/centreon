import { Method, type ResponseError, useMutationQuery } from '@centreon/ui';

import type { JsonDecoder } from 'ts.data.json';

import { baseEndpoint } from '../../../api/endpoint';

// The errors these calls can return for an expected reason (invalid input,
// unknown provider, a provider that refused the request): the form shows the
// message next to its button rather than in the error snackbar.
const inlineErrorCodes = [400, 404, 409, 422, 502];

export type ProviderCallResult<T> =
  | { data: T; error: null }
  | { data: null; error: string };

interface Props<T> {
  decoder: JsonDecoder.Decoder<T>;
  endpoint: string;
}

interface ProviderCallState<T> {
  call: (payload: Record<string, unknown>) => Promise<ProviderCallResult<T>>;
  isCalling: boolean;
}

// A POST the form sends with the configuration being edited, without saving
// it: connection test, model discovery, price lookup.
const useProviderCall = <T extends object>({
  decoder,
  endpoint
}: Props<T>): ProviderCallState<T> => {
  const { mutateAsync, isMutating } = useMutationQuery<T, undefined>({
    baseEndpoint,
    decoder,
    getEndpoint: () => endpoint,
    httpCodesBypassErrorSnackbar: inlineErrorCodes,
    method: Method.POST
  });

  const call = async (
    payload: Record<string, unknown>
  ): Promise<ProviderCallResult<T>> => {
    // the request body is not the response type the hook is typed with
    const response = await mutateAsync({ payload: payload as unknown as T });

    if ((response as ResponseError).isError) {
      return { data: null, error: (response as ResponseError).message };
    }

    return { data: response as T, error: null };
  };

  return { call, isCalling: isMutating };
};

export default useProviderCall;
