import { useFetchQuery } from '@centreon/ui';
import { useSetAtom } from 'jotai';
import { useValidationSchema } from '../Form/useValidationSchema';
import { getVaultConfigurationDecoder } from '../api/decoders';
import { vaultConfigurationEndpoint } from '../api/endpoints';
import { canMigrateAtom } from '../atoms';

export const useGetVaultConfiguration = () => {
  const setCanMigrate = useSetAtom(canMigrateAtom);

  const validationSchema = useValidationSchema();

  const { data, isLoading } = useFetchQuery({
    decoder: getVaultConfigurationDecoder,
    getEndpoint: () => vaultConfigurationEndpoint,
    getQueryKey: () => ['vault-configuration'],
    httpCodesBypassErrorSnackbar: [404],
    queryOptions: {
      suspense: false
    }
  });

  if (data) {
    setCanMigrate(
      validationSchema.isValidSync({ ...data, secretId: 'secret' })
    );
  }

  return { data, isLoading };
};
