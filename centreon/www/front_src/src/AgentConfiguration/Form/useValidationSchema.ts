import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { array, boolean, mixed, number, object, Schema, string } from 'yup';

import { AgentConfigurationForm, AgentType } from '../models';
import {
  labelAddressInvalid,
  labelAtLeastOneConnexionMode,
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
const validCertificateExtensionRegex = /\.(crt|cer)$/;
const validFileExtensionRegex = /\.key$/;
const relativePathRegex = /^\.{1,2}\//;

export const useValidationSchema = (): Schema<AgentConfigurationForm> => {
  const { t } = useTranslation();

  const requiredString = useMemo(() => string().required(t(labelRequired)), []);

  const certificateFileValidation = (isFile?: boolean) =>
    string()
      .test({
        message: t(labelInvalidPath),
        name: 'invalid-path',
        test: (value) => !value || invalidPath.test(value)
      })
      .test({
        message: t(labelRelativePathAreNotAllowed),
        name: 'is-not-relative-path',
        test: (value) => !value || !relativePathRegex.test(value)
      })
      .test({
        message: t(labelInvalidExtension),
        name: 'has-valid-extension',
        test: (value) =>
          !value ||
          (isFile
            ? validFileExtensionRegex.test(value)
            : validCertificateExtensionRegex.test(value))
      });

  const certificateValidation = (isFile?: boolean) =>
    string().when('$connectionMode.id', {
      is: (value: string) =>
        equals(value, 'secure') || equals(value, 'insecure'),
      otherwise: () => string().nullable(),
      // biome-ignore lint/suspicious/noThenProperty: false positive
      then: () => certificateFileValidation(isFile).nullable()
    });

  const portValidation = number()
    .min(1, t(labelPortMustStartFrom1))
    .max(65535, t(labelPortExpectedAtMost))
    .required(t(labelRequired));

  const telegrafConfigurationSchema = {
    confCertificate: certificateValidation(),
    confPrivateKey: certificateValidation(true),
    confServerPort: portValidation,
    otelCaCertificate: certificateValidation(),
    otelPrivateKey: certificateValidation(true),
    otelPublicCertificate: certificateValidation()
  };

  const CMAConfigurationSchema = {
    agentInitiated: boolean(),
    hosts: array()
      .of(
        object({
          address: string()
            .test({
              exclusive: true,
              message: t(labelAddressInvalid),
              name: 'is-dns-ip-valid',
              test: (address) =>
                address?.match(ipAddressRegex) || address?.match(urlRegex)
            })
            .required(t(labelRequired)),
          pollerCaCertificate: certificateValidation(),
          pollerCaName: string().nullable(),
          port: portValidation,
          token: object().when(['$type', '$configuration'], {
            is: (type, configuration) =>
              configuration?.pollerInitiated && equals(type?.id, AgentType.CMA),
            otherwise: (schema) => schema.nullable(),
            // biome-ignore lint/suspicious/noThenProperty: false positive
            then: (schema) =>
              schema
                .shape({
                  creatorId: number(),
                  id: string(),
                  name: string(),
                  token_name: string()
                })
                .required(t(labelRequired))
          })
        })
      )
      .when('pollerInitiated', {
        is: true,
        otherwise: (schema) => schema.min(0),
        // biome-ignore lint/suspicious/noThenProperty: false positive
        then: (schema) => schema.min(1)
      }),
    otelCaCertificate: certificateValidation(),
    otelPrivateKey: certificateValidation(true),
    otelPublicCertificate: certificateValidation(),
    pollerInitiated: boolean(),
    port: number()
      .min(1, t(labelPortMustStartFrom1))
      .max(65535, t(labelPortExpectedAtMost))
      .when('agentInitiated', {
        is: true,
        otherwise: (schema) => schema.nullable().notRequired(),
        // biome-ignore lint/suspicious/noThenProperty: false positive
        then: (schema) => schema.required(t(labelRequired))
      }),
    tokens: array().when(['$type', 'agentInitiated'], {
      is: (type, agentInitiated) =>
        agentInitiated && equals(type?.id, AgentType.CMA),
      otherwise: (schema) => schema.nullable(),
      // biome-ignore lint/suspicious/noThenProperty: false positive
      then: (schema) =>
        schema
          .of(
            object({
              creatorId: number(),
              id: string(),
              name: string()
            })
          )
          .min(1, t(labelRequired))
          .required()
    })
  };

  return object<AgentConfigurationForm>({
    configuration: object().when('type', {
      is: (type) => equals(type?.id, AgentType.Telegraf),
      otherwise: (schema) =>
        schema.shape(CMAConfigurationSchema).test({
          message: t(labelAtLeastOneConnexionMode),
          name: 'at-least-one-initiated',
          test: (config) => config?.agentInitiated || config?.pollerInitiated
        }),
      // biome-ignore lint/suspicious/noThenProperty: false positive
      then: (schema) => schema.shape(telegrafConfigurationSchema)
    }),
    connectionMode: object({
      id: string(),
      name: string()
    }).nullable(),
    name: requiredString,
    pollers: array()
      .of(
        object({
          id: number(),
          name: string()
        })
      )
      .min(1, t(labelRequired)),
    type: mixed().required(t(labelRequired))
  });
};
