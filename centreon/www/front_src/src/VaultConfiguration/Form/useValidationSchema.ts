import { useTranslation } from 'react-i18next';
import { number, object, type Schema, string } from 'yup';

import { PostVaultConfiguration } from '../models';
import {
  labelAddressIsNotAnUrl,
  labelPortExpectedAtMost,
  labelPortMustStartFrom1,
  labelRequired
} from '../translatedLabels';

const urlRegex = /^[a-zA-Z0-9_-]+\.?[a-zA-Z0-9-_.]+\.?[a-zA-Z0-9-_]+$/;
export const portRegex = /:[0-9]+$/;

export const useValidationSchema = (): Schema<PostVaultConfiguration> => {
  const { t } = useTranslation();

  const validationSchema = object({
    address: string()
      .test({
        exclusive: true,
        message: t(labelAddressIsNotAnUrl),
        name: 'is-valid-address',
        test: (address) => address?.match(urlRegex) && !address.match(portRegex)
      })
      .required(t(labelRequired)),
    port: number()
      .min(1, t(labelPortMustStartFrom1))
      .max(65535, t(labelPortExpectedAtMost))
      .required(t(labelRequired)),
    roleId: string().required(t(labelRequired)),
    rootPath: string().required(t(labelRequired)),
    secretId: string().required(t(labelRequired))
  });

  return validationSchema;
};
