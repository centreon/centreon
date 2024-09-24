import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Schema, array, boolean, mixed, number, object, string } from 'yup';
import { AgentConfigurationForm, AgentType } from '../models';
import {
  labelAddressInvalid,
  labelExtensionNotAllowed,
  labelPortExpectedAtMost,
  labelPortMustStartFrom1,
  labelRequired
} from '../translatedLabels';

const ipAddressRegex = /^((25[0-5]|(2[0-4]|1\d|[1-9]|)\d)\.?\b){4}$/;
const urlRegex = /^[a-zA-Z0-9]+\.?[a-zA-Z0-9.]+\.?[a-zA-Z0-9]+$/;
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
  const portValidation = number()
    .min(1, t(labelPortMustStartFrom1))
    .max(65535, t(labelPortExpectedAtMost))
    .required(t(labelRequired));

  const telegrafConfigurationSchema = {
    confServerPort: portValidation,
    otelPublicCertificate: filenameValidation,
    otelCaCertificate: filenameValidation,
    otelPrivateKey: filenameValidation,
    confCertificate: filenameValidation,
    confPrivateKey: filenameValidation
  };

  const CMAConfigurationSchema = {
    isReverse: boolean(),
    otlpCertificate: requiredString,
    otlpCaCertificate: requiredString,
    otlpPrivateKey: requiredString,
    hosts: array().when('isReverse', {
      is: true,
      // biome-ignore lint/suspicious/noThenProperty: <explanation>
      then: (schema) =>
        schema
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
              certificate: requiredString,
              key: requiredString
            })
          )
          .min(1),
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
