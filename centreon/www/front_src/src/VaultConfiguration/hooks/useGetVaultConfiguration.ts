import { useFetchQuery } from '@centreon/ui';

import { useSetAtom } from 'jotai';

import { getVaultConfigurationDecoder } from '../api/decoders';
import { vaultConfigurationEndpoint } from '../api/endpoints';
import { canMigrateAtom } from '../atoms';
import { useValidationSchema } from '../Form/useValidationSchema';

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
      validationSchema.isValidSync({
        ...data,
        secretId: 'secret',
        roleId: 'role'
      })
    );
  }

  return { data, isLoading };
};
