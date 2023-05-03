import { useTranslation } from 'react-i18next';
import * as Yup from 'yup';

import { NamedEntity } from '../shared/models';

import { SAMLConfiguration } from './models';
import { labelRequired, labelInvalidURL } from './translatedLabels';

const urlRegexp = /https?:\/\/(\S+)/;

const useValidationSchema = (): Yup.SchemaOf<SAMLConfiguration> => {
  const { t } = useTranslation();

  const namedEntitySchema: Yup.SchemaOf<NamedEntity> = Yup.object({
    id: Yup.number().required(t(labelRequired)),
    name: Yup.string().required(t(labelRequired))
  });

  const rolesRelationSchema = Yup.object({
    accessGroup: namedEntitySchema.nullable().required(t(labelRequired)),
    claimValue: Yup.string().required(t(labelRequired)),
    priority: Yup.number().required(t(labelRequired))
  });

  const groupsRelationSchema = Yup.object({
    contactGroup: namedEntitySchema.nullable().required(t(labelRequired)),
    groupValue: Yup.string().required(t(labelRequired))
  });

  const switchSchema = Yup.boolean().required(t(labelRequired));

  return Yup.object({
    authenticationConditions: Yup.object({
      attributePath: Yup.string(),
      authorizedValues: Yup.array().of(Yup.string().defined()),
      isEnabled: switchSchema
    }),
    autoImport: switchSchema,
    certificate: Yup.string().required(t(labelRequired)),
    contactTemplate: namedEntitySchema
      .when('autoImport', (autoImport, schema) => {
        return autoImport
          ? schema.nullable().required(t(labelRequired))
          : schema.nullable();
      })
      .defined(),
    emailBindAttribute: Yup.string().when(
      'autoImport',
      (autoImport, schema) => {
        return autoImport
          ? schema.nullable().required(t(labelRequired))
          : schema.nullable();
      }
    ),
    entityIdUrl: Yup.string()
      .matches(urlRegexp, t(labelInvalidURL))
      .required(t(labelRequired)),
    fullnameBindAttribute: Yup.string().when(
      'autoImport',
      (autoImport, schema) => {
        return autoImport
          ? schema.nullable().required(t(labelRequired))
          : schema.nullable();
      }
    ),
    groupsMapping: Yup.object({
      attributePath: Yup.string(),
      isEnabled: switchSchema,
      relations: Yup.array().of(groupsRelationSchema)
    }),
    isActive: switchSchema,
    isForced: switchSchema,
    logoutFrom: switchSchema,
    logoutFromUrl: Yup.string()
      .matches(urlRegexp, t(labelInvalidURL))
      .when('logoutFrom', {
        is: true,
        otherwise: (schema) => schema.nullable(),
        then: (schema) => schema.required(t(labelRequired))
      }),
    remoteLoginUrl: Yup.string()
      .matches(urlRegexp, t(labelInvalidURL))
      .required(t(labelRequired)),
    rolesMapping: Yup.object({
      applyOnlyFirstRole: switchSchema,
      attributePath: Yup.string(),
      isEnabled: switchSchema,
      relations: Yup.array().of(rolesRelationSchema)
    }),
    userIdAttribute: Yup.string().required(t(labelRequired))
  });
};

export default useValidationSchema;
