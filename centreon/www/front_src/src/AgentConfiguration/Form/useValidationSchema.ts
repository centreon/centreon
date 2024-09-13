import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Schema, array, mixed, number, object, string } from 'yup';
import {
  AgentConfigurationConfigurationForm,
  AgentConfigurationForm
} from '../models';
import {
  labelAddressInvalid,
  labelPortExpectedAtMost,
  labelPortMustStartFrom1,
  labelRequired
} from '../translatedLabels';

const ipAddressRegex = /^((25[0-5]|(2[0-4]|1\d|[1-9]|)\d)\.?\b){4}$/;
export const portRegex = /:[0-9]+$/;

export const useValidationSchema = (): Schema<AgentConfigurationForm> => {
  const { t } = useTranslation();

  const requiredString = useMemo(() => string().required(t(labelRequired)), []);
  const requiredNumber = useMemo(() => number().required(t(labelRequired)), []);
  const requiredMixed = useMemo(() => mixed().required(t(labelRequired)), []);

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
    configuration: object<AgentConfigurationConfigurationForm>({
      otelServerAddress: string()
        .test({
          name: 'is-address-valid',
          message: t(labelAddressInvalid),
          exclusive: true,
          test: (address) =>
            address?.match(ipAddressRegex) && !address.match(portRegex)
        })
        .required(t(labelRequired)),
      otelServerPort: number()
        .min(1, t(labelPortMustStartFrom1))
        .max(65535, t(labelPortExpectedAtMost))
        .required(t(labelRequired)),
      confServerPort: requiredNumber
    }),
    files: object({
      otelPublicCertificate: requiredMixed,
      otelCaCertificate: requiredMixed,
      otelPrivateKey: requiredMixed,
      confCertificate: requiredMixed,
      confPrivateKey: requiredMixed
    })
  });
};
