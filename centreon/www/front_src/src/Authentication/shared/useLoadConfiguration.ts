import { JsonDecoder } from 'ts.data.json';

import { useFetchQuery } from '@centreon/ui';

import { Provider } from '../models';
import { authenticationProvidersEndpoint } from '../api/endpoints';

interface UseLoadConfigurationState<T> {
  initialConfiguration?: T;
  loadConfiguration: () => void;
  sendingGetConfiguration: boolean;
}

interface UseLoadConfigurationProps<T> {
  decoder?: JsonDecoder.Decoder<T>;
  providerType: Provider;
}

const useLoadConfiguration = <T extends object>({
  providerType,
  decoder
}: UseLoadConfigurationProps<T>): UseLoadConfigurationState<T> => {
  const {
    data,
    fetchQuery: loadConfiguration,
    isLoading
  } = useFetchQuery<T>({
    decoder,
    getEndpoint: () => authenticationProvidersEndpoint(providerType),
    getQueryKey: () => [providerType],
    queryOptions: {
      suspense: false
    }
  });

  return {
    initialConfiguration: data,
    loadConfiguration,
    sendingGetConfiguration: isLoading
  };
};

export default useLoadConfiguration;
