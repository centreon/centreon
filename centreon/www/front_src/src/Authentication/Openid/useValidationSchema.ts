import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { Schema, array, boolean, mixed, number, object, string } from 'yup';

import { EndpointType, NamedEntity, OpenidConfiguration } from './models';
import {
  labelInvalidIPAddress,
  labelInvalidURL,
  labelRequired
} from './translatedLabels';

const IPAddressRegexp = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(\/\d{1,3})?$/;

const urlRegexp = /https?:\/\/(\S+)/;

const useValidationSchema = (): Schema<OpenidConfiguration> => {
  const { t } = useTranslation();

  const namedEntitySchema: Schema<NamedEntity> = object({
    id: number().required(t(labelRequired)),
    name: string().required(t(labelRequired))
  });

  const rolesRelationSchema = object({
    accessGroup: namedEntitySchema.nullable().required(t(labelRequired)),
    claimValue: string().required(t(labelRequired)),
    priority: number().required(t(labelRequired))
  });

  const groupsRelationSchema = object({
    contactGroup: namedEntitySchema.nullable().required(t(labelRequired)),
    groupValue: string().required(t(labelRequired))
  });

  const endpointTypeSchema = mixed<EndpointType>()
    .oneOf(Object.values(EndpointType))
    .required(t(labelRequired));
  const switchSchema = boolean().required(t(labelRequired));
  const endpointSchema = object({
    customEndpoint: string().when('type', ([type], schema) => {
      if (equals(type, EndpointType.CustomEndpoint)) {
        return schema.required(t(labelRequired));
      }

      return schema.nullable();
    }),
    type: endpointTypeSchema
  });

  return object({
    authenticationConditions: object({
      attributePath: string(),
      authorizedValues: array().of(string().defined()),
      blacklistClientAddresses: array().of(
        string()
          .matches(IPAddressRegexp, t(labelInvalidIPAddress))
          .required(t(labelRequired))
      ),
      endpoint: endpointSchema,
      isEnabled: switchSchema,
      trustedClientAddresses: array().of(
        string()
          .matches(IPAddressRegexp, t(labelInvalidIPAddress))
          .required(t(labelRequired))
      )
    }),
    authenticationType: string().required(t(labelRequired)),
    authorizationEndpoint: string().nullable().required(t(labelRequired)),
    autoImport: switchSchema,
    baseUrl: string()
      .matches(urlRegexp, t(labelInvalidURL))
      .nullable()
      .required(t(labelRequired)),
    claimName: string().nullable(),
    clientId: string().nullable().required(t(labelRequired)),
    clientSecret: string().nullable().required(t(labelRequired)),
    connectionScopes: array().of(string().required(t(labelRequired))),
    contactTemplate: namedEntitySchema
      .when('autoImport', ([autoImport], schema) => {
        return autoImport
          ? schema.nullable().required(t(labelRequired))
          : schema.nullable();
      })
      .defined(),
    emailBindAttribute: string().when('autoImport', ([autoImport], schema) => {
      return autoImport
        ? schema.nullable().required(t(labelRequired))
        : schema.nullable();
    }),
    endSessionEndpoint: string().nullable(),
    fullnameBindAttribute: string().when(
      'autoImport',
      ([autoImport], schema) => {
        return autoImport
          ? schema.nullable().required(t(labelRequired))
          : schema.nullable();
      }
    ),
    groupsMapping: object({
      attributePath: string(),
      endpoint: endpointSchema,
      isEnabled: switchSchema,
      relations: array().of(groupsRelationSchema)
    }),
    introspectionTokenEndpoint: string().nullable(),
    isActive: switchSchema,
    isForced: switchSchema,
    loginClaim: string().nullable(),
    redirectUrl: string().nullable(),
    rolesMapping: object({
      applyOnlyFirstRole: switchSchema,
      attributePath: string(),
      endpoint: endpointSchema,
      isEnabled: switchSchema,
      relations: array().of(rolesRelationSchema)
    }),
    tokenEndpoint: string().nullable().required(t(labelRequired)),
    userinfoEndpoint: string().nullable(),
    verifyPeer: switchSchema
  });
};

export default useValidationSchema;
