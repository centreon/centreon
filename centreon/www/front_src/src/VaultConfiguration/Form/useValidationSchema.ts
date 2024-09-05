import { useTranslation } from 'react-i18next';
import { type Schema, number, object, string } from 'yup';
import { PostVaultConfiguration } from '../models';
import { labelAddressIsNotAnUrl, labelRequired } from '../translatedLabels';

const urlRegex = /^[a-zA-Z0-9]+\.?[a-zA-Z0-9.]+\.?[a-zA-Z0-9]+$/;
export const portRegex = /:[0-9]+$/;

export const useValidationSchema = (): Schema<PostVaultConfiguration> => {
  const { t } = useTranslation();

  const validationSchema = object({
    address: string()
      .test({
        name: 'is-valid-address',
        message: t(labelAddressIsNotAnUrl),
        test: (address) =>
          address?.match(urlRegex) && !address.match(portRegex),
        exclusive: true
      })
      .required(t(labelRequired)),
    port: number().required(t(labelRequired)),
    rootPath: string().required(t(labelRequired)),
    roleId: string().required(t(labelRequired)),
    secretId: string().required(t(labelRequired))
  });

  return validationSchema;
};
