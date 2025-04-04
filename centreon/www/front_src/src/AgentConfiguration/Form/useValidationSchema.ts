import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Schema, array, boolean, mixed, number, object, string } from 'yup';
import { AgentConfigurationForm, AgentType } from '../models';
import {
  labelAddressInvalid,
  labelInvalidExtension,
  labelInvalidPath,
  labelPortExpectedAtMost,
  labelPortMustStartFrom1,
  labelRelativePathAreNotAllowed,
  labelRequired
} from '../translatedLabels';

const ipAddressRegex = /^((25[0-5]|(2[0-4]|1\d|[1-9]|)\d)\.?\b){4}$/;
const urlRegex = /^[a-zA-Z0-9_-]+\.?[a-zA-Z0-9-_.]+\.?[a-zA-Z0-9-_]+$/;
export const portRegex = /:[0-9]+$/;
export const keyFilenameRegexp = /^[a-zA-Z0-9-_.]+(?<!\.key)$/;

const invalidPath = /^(?!.*\/\/).+$/;
const validExtensionRegex = /\.(crt|key|cer)$/;
const relativePathRegex = /^\.{1,2}\//;

export const useValidationSchema = (): Schema<AgentConfigurationForm> => {
  const { t } = useTranslation();

  const requiredString = useMemo(() => string().required(t(labelRequired)), []);

  const certificateValidation = useMemo(
    () =>
      string()
        .test({
          name: 'invalid-path',
          message: t(labelInvalidPath),
          test: (value) => !value || invalidPath.test(value)
        })
        .test({
          name: 'is-not-relative-path',
          message: t(labelRelativePathAreNotAllowed),
          test: (value) => !value || !relativePathRegex.test(value)
        })
        .test({
          name: 'has-valid-extension',
          message: t(labelInvalidExtension),
          test: (value) => !value || validExtensionRegex.test(value)
        }),
    []
  );

  const portValidation = number()
    .min(1, t(labelPortMustStartFrom1))
    .max(65535, t(labelPortExpectedAtMost))
    .required(t(labelRequired));
  const certificateNullableValidation = certificateValidation.nullable();

  const telegrafConfigurationSchema = {
    confServerPort: portValidation,
    otelPublicCertificate: certificateValidation.required(t(labelRequired)),
    otelCaCertificate: certificateNullableValidation,
    otelPrivateKey: certificateValidation.required(t(labelRequired)),
    confCertificate: certificateValidation.required(t(labelRequired)),
    confPrivateKey: certificateValidation.required(t(labelRequired))
  };

  const CMAConfigurationSchema = {
    isReverse: boolean(),
    otelPublicCertificate: certificateValidation.required(t(labelRequired)),
    otelCaCertificate: certificateValidation.nullable(),
    otelPrivateKey: certificateValidation.required(t(labelRequired)),
    hosts: array()
      .of(
        object({
          address: string()
            .test({
              name: 'is-dns-ip-valid',
              exclusive: true,
              message: t(labelAddressInvalid),
              test: (address) =>
                address?.match(ipAddressRegex) || address?.match(urlRegex)
            })
            .required(t(labelRequired)),
          port: portValidation,
          pollerCaCertificate: certificateNullableValidation,
          pollerCaName: string().nullable()
        })
      )
      .when('isReverse', {
        is: true,
        // biome-ignore lint/suspicious/noThenProperty:
        then: (schema) => schema.min(1),
        otherwise: (schema) => schema.min(0)
      })
  };

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
    configuration: object().when('type', {
      is: (type) => equals(type?.id, AgentType.Telegraf),
      // biome-ignore lint/suspicious/noThenProperty: <explanation>
      then: (schema) => schema.shape(telegrafConfigurationSchema),
      otherwise: (schema) => schema.shape(CMAConfigurationSchema)
    })
  });
};
