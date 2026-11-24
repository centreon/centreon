import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { useTranslation } from 'react-i18next';
import type { JsonDecoder } from 'ts.data.json';

import { labelUnreadableAnswer } from '../translatedLabels';

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
  const { t } = useTranslation();
  const { showErrorMessage } = useSnackbar();

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

    // a body that is not JSON comes back as null, without any snackbar
    if (response === null) {
      showErrorMessage(t(labelUnreadableAnswer));

      return null;
    }

    if ('isError' in response) {
      return null;
    }

    return response;
  };

  return { call, isMutating };
};
