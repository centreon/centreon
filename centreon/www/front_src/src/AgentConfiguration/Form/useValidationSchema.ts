import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Schema, array, mixed, number, object, string } from 'yup';
import {
  AgentConfigurationConfiguration,
  AgentConfigurationForm
} from '../models';
import {
  labelInvalidFilename,
  labelPortExpectedAtMost,
  labelPortMustStartFrom1,
  labelRequired
} from '../translatedLabels';

export const portRegex = /:[0-9]+$/;
export const certificateFilenameRegexp = /^[a-zA-Z0-9-_.]+(?<!\.crt|cert|cer)$/;
export const keyFilenameRegexp = /^[a-zA-Z0-9-_.]+(?<!\.key)$/;

export const useValidationSchema = (): Schema<AgentConfigurationForm> => {
  const { t } = useTranslation();

  const requiredString = useMemo(() => string().required(t(labelRequired)), []);
  const certificateValidation = string()
    .matches(certificateFilenameRegexp, t(labelInvalidFilename))
    .required(t(labelRequired));
  const keyValidation = string()
    .matches(keyFilenameRegexp, t(labelInvalidFilename))
    .required(t(labelRequired));
  const certificateNullableValidation = string()
    .matches(certificateFilenameRegexp, t(labelInvalidFilename))
    .nullable();

  return object<AgentConfigurationForm>({
    name: requiredString,
    type: mixed().required(t(labelRequired)),
    pollers: array()
      .of(
        object({
          id: number(),
          name: string()
        })
      )
      .min(1, t(labelRequired)),
    configuration: object<AgentConfigurationConfiguration>({
      confServerPort: number()
        .min(1, t(labelPortMustStartFrom1))
        .max(65535, t(labelPortExpectedAtMost))
        .required(t(labelRequired)),
      otelPublicCertificate: certificateValidation,
      otelCaCertificate: certificateNullableValidation,
      otelPrivateKey: keyValidation,
      confCertificate: certificateValidation,
      confPrivateKey: keyValidation
    })
  });
};
