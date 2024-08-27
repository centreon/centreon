import { useTranslation } from 'react-i18next';
import { Schema, array, boolean, number, object, string } from 'yup';

import { NamedEntity } from '../shared/models';

import { SAMLConfiguration } from './models';
import { labelInvalidURL, labelRequired } from './translatedLabels';

const urlRegexp = /https?:\/\/(\S+)/;

const useValidationSchema = (): Schema<SAMLConfiguration> => {
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

  const switchSchema = boolean().required(t(labelRequired));

  return object({
    authenticationConditions: object({
      attributePath: string(),
      authorizedValues: array().of(string().defined()),
      isEnabled: switchSchema
    }),
    autoImport: switchSchema,
    certificate: string().required(t(labelRequired)),
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
    entityIdUrl: string()
      .matches(urlRegexp, t(labelInvalidURL))
      .required(t(labelRequired)),
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
      isEnabled: switchSchema,
      relations: array().of(groupsRelationSchema)
    }),
    isActive: switchSchema,
    isForced: switchSchema,
    logoutFrom: switchSchema,
    logoutFromUrl: string()
      .matches(urlRegexp, t(labelInvalidURL))
      .when('logoutFrom', ([logoutFrom], schema) => {
        if (logoutFrom) {
          return schema.required(t(labelRequired));
        }

        return schema.nullable();
      }),
    remoteLoginUrl: string()
      .matches(urlRegexp, t(labelInvalidURL))
      .required(t(labelRequired)),
    rolesMapping: object({
      applyOnlyFirstRole: switchSchema,
      attributePath: string(),
      isEnabled: switchSchema,
      relations: array().of(rolesRelationSchema)
    }),
    userIdAttribute: string().required(t(labelRequired))
  });
};

export default useValidationSchema;
