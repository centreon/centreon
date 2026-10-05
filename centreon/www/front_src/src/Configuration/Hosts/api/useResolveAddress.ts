import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { useTranslation } from 'react-i18next';
import { JsonDecoder } from 'ts.data.json';

import { labelHostNotFound } from '../translatedLabels';
import { getResolveAddressEndpoint, hostsBaseEndpoint } from './endpoints';

interface Resolution {
  ip?: string;
  resolved: boolean;
}

// Kept out of `decoders`, which imports the sections that render this: no
// cycle.
const resolutionDecoder = JsonDecoder.object<Resolution>(
  {
    // Left out of the response when the name does not resolve.
    ip: JsonDecoder.optional(JsonDecoder.string),
    resolved: JsonDecoder.boolean
  },
  'Address resolution'
);

interface UseResolveAddressState {
  isResolving: boolean;
  resolve: (hostname: string) => Promise<string | null>;
}

// A refused address answers 422 with the reason, which the error snackbar
// shows as is.
const useResolveAddress = (): UseResolveAddressState => {
  const { t } = useTranslation();
  const { showErrorMessage } = useSnackbar();

  const { mutateAsync, isMutating } = useMutationQuery<
    Resolution,
    { hostname: string }
  >({
    baseEndpoint: hostsBaseEndpoint,
    decoder: resolutionDecoder,
    getEndpoint: getResolveAddressEndpoint,
    method: Method.GET
  });

  const resolve = async (hostname: string): Promise<string | null> => {
    const response = await mutateAsync({ _meta: { hostname } });

    if ('isError' in response) {
      return null;
    }

    if (!response.resolved || !response.ip) {
      showErrorMessage(t(labelHostNotFound));

      return null;
    }

    return response.ip;
  };

  return { isResolving: isMutating, resolve };
};

export default useResolveAddress;
