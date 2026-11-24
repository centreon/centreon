import { Method, type ResponseError, useMutationQuery } from '@centreon/ui';

import { useTranslation } from 'react-i18next';
import type { JsonDecoder } from 'ts.data.json';

import { baseEndpoint } from '../../../api/endpoint';
import { labelUnreadableAnswer } from '../translatedLabels';

// The errors whose message the form shows next to its button rather than in
// the error snackbar: invalid input (400, 422), an unknown provider (404), a
// conflict (409) and a failure of the provider itself (502).
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
  const { t } = useTranslation();

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

    // a body that is not JSON comes back as null
    if (response === null) {
      return { data: null, error: t(labelUnreadableAnswer) };
    }

    if ((response as ResponseError).isError) {
      return { data: null, error: (response as ResponseError).message };
    }

    return { data: response as T, error: null };
  };

  return { call, isCalling: isMutating };
};

export default useProviderCall;
