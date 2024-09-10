import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';
import type { FormikHelpers } from 'formik';
import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { vaultConfigurationEndpoint } from '../api/endpoints';
import { canMigrateAtom } from '../atoms';
import { PostVaultConfiguration, PostVaultConfigurationAPI } from '../models';
import { labelVaultConfigurationUpdate } from '../translatedLabels';

const formatVaultConfiguration = (
  configuration: PostVaultConfiguration
): PostVaultConfigurationAPI => ({
  address: configuration.address,
  port: Number(configuration.port),
  root_path: configuration.rootPath,
  role_id: configuration.roleId,
  secret_id: configuration.secretId
});

export const useUpdateVaultConfiguration = () => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const setCanMigrate = useSetAtom(canMigrateAtom);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => vaultConfigurationEndpoint,
    method: Method.PUT,
    onMutate: ({ _meta }) => {
      _meta.setSubmitting(true);
    },
    onSettled: (_data, _error, { _meta }) => {
      _meta.setSubmitting(false);
    },
    onSuccess: (_data, { _meta, payload }) => {
      _meta.resetForm({
        values: {
          ...payload,
          rootPath: payload.root_path,
          roleId: payload.role_id,
          secretId: ''
        }
      });
      setCanMigrate(true);
      showSuccessMessage(t(labelVaultConfigurationUpdate));
    },
    onError: () => {
      setCanMigrate(false);
    }
  });

  const submitVaultConfiguration = (
    values,
    { setSubmitting, resetForm }: FormikHelpers<PostVaultConfiguration>
  ) => {
    const payload = formatVaultConfiguration(values);
    setSubmitting(true);

    mutateAsync({
      payload,
      _meta: {
        setSubmitting,
        resetForm
      }
    });
  };

  return submitVaultConfiguration;
};
