import { useTranslation } from 'react-i18next';
import { type Schema, array, boolean, string } from 'yup';

import { WebSSOConfiguration } from './models';
import {
  labelInvalidIPAddress,
  labelInvalidRegex,
  labelRequired
} from './translatedLabels';

const IpAddressRegexp = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(\/\d{1,3})?$/;
const matchaRegexp = /^\^?[^(^|$);]+\$?$/;

const useValidationSchema = (): Schema<WebSSOConfiguration> => {
  const { t } = useTranslation();

  return object().shape({
    blacklistClientAddresses: array().of(
      string()
        .matches(IpAddressRegexp, t(labelInvalidIPAddress))
        .required(t(labelRequired))
    ),
    isActive: boolean().required(t(labelRequired)),
    isForced: boolean().required(t(labelRequired)),
    loginHeaderAttribute: string().nullable().required(t(labelRequired)),
    patternMatchingLogin: string()
      .matches(matchaRegexp, t(labelInvalidRegex))
      .nullable(),
    patternReplaceLogin: string()
      .matches(matchaRegexp, t(labelInvalidRegex))
      .nullable(),
    trustedClientAddresses: array().of(
      string()
        .matches(IpAddressRegexp, t(labelInvalidIPAddress))
        .required(t(labelRequired))
    )
  });
};

export default useValidationSchema;
