import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Schema, array, boolean, mixed, number, object, string } from 'yup';
import { AgentConfigurationForm, AgentType, ConnectionMode } from '../models';
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

  const certificateFileValidation = useMemo(
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

  const certificateValidation = string().when('$connectionMode.id', {
    is: (value: string) => equals(value, 'secure') || equals(value, 'insecure'),
    // biome-ignore lint/suspicious/noThenProperty: <explanation>
    then: () => certificateFileValidation.required(t(labelRequired)),
    otherwise: () => string().nullable()
  });

  const certificateNullableValidation = string().when('$connectionMode.id', {
    is: (value: string) => equals(value, 'secure') || equals(value, 'insecure'),
    // biome-ignore lint/suspicious/noThenProperty: <explanation>
    then: () => certificateFileValidation.nullable(),
    otherwise: () => string().nullable()
  });

  const portValidation = number()
    .min(1, t(labelPortMustStartFrom1))
    .max(65535, t(labelPortExpectedAtMost))
    .required(t(labelRequired));

  const telegrafConfigurationSchema = {
    confServerPort: portValidation,
    otelPublicCertificate: certificateValidation,
    otelCaCertificate: certificateNullableValidation,
    otelPrivateKey: certificateValidation,
    confCertificate: certificateValidation,
    confPrivateKey: certificateValidation
  };

  const CMAConfigurationSchema = {
    isReverse: boolean(),
    tokens: array().when(['$type', '$connectionMode', 'isReverse'], {
      is: (type, connectionMode, isReverse) =>
        !isReverse &&
        equals(type?.id, AgentType.CMA) &&
        equals(connectionMode?.id, ConnectionMode.secure),
      // biome-ignore lint/suspicious/noThenProperty: <explanation>
      then: (schema) =>
        schema
          .of(
            object({
              id: string(),
              name: string(),
              creatorId: number()
            })
          )
          .min(1, t(labelRequired))
          .required(),
      otherwise: (schema) => schema.nullable()
    }),
    otelPublicCertificate: certificateValidation,
    otelCaCertificate: certificateNullableValidation,
    otelPrivateKey: certificateValidation,
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
        // biome-ignore lint/suspicious/noThenProperty: <explanation>
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
    connectionMode: object({
      id: string(),
      name: string()
    }).nullable(),
    configuration: object().when('type', {
      is: (type) => equals(type?.id, AgentType.Telegraf),
      // biome-ignore lint/suspicious/noThenProperty: <explanation>
      then: (schema) => schema.shape(telegrafConfigurationSchema),
      otherwise: (schema) => schema.shape(CMAConfigurationSchema)
    })
  });
};
