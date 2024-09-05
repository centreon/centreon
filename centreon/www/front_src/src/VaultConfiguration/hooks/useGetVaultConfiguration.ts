import { useFetchQuery } from '@centreon/ui';
import { getVaultConfigurationDecoder } from '../api/decoders';
import { vaultConfigurationEndpoint } from '../api/endpoints';

export const useGetVaultConfiguration = () => {
  const { data, isLoading } = useFetchQuery({
    decoder: getVaultConfigurationDecoder,
    getEndpoint: () => vaultConfigurationEndpoint,
    getQueryKey: () => ['vault-configuration'],
    httpCodesBypassErrorSnackbar: [404],
    queryOptions: {
      suspense: false
    }
  });

  return { data, isLoading };
};
