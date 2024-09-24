import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Schema, array, mixed, number, object, string } from 'yup';
import {
  AgentConfigurationConfiguration,
  AgentConfigurationForm
} from '../models';
import {
  labelExtensionNotAllowed,
  labelPortExpectedAtMost,
  labelPortMustStartFrom1,
  labelRequired
} from '../translatedLabels';

export const portRegex = /:[0-9]+$/;

export const useValidationSchema = (): Schema<AgentConfigurationForm> => {
  const { t } = useTranslation();

  const requiredString = useMemo(() => string().required(t(labelRequired)), []);
  const filenameValidation = useMemo(
    () =>
      requiredString.test({
        name: 'is-filename-valid',
        exclusive: true,
        message: t(labelExtensionNotAllowed),
        test: (filename) => !filename.match(/\.(pem|crt|key|cer)$/)
      }),
    []
  );

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
      otelPublicCertificate: filenameValidation,
      otelCaCertificate: filenameValidation,
      otelPrivateKey: filenameValidation,
      confCertificate: filenameValidation,
      confPrivateKey: filenameValidation
    })
  });
};
