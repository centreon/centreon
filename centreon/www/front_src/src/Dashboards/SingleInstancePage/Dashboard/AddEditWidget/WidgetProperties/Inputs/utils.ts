import type { FormikValues } from 'formik';
import type { TFunction } from 'i18next';
import {
  path,
  always,
  cond,
  equals,
  includes,
  isEmpty,
  pluck,
  split
} from 'ramda';

import {
  type AnyObjectSchema,
  type AnySchema,
  type ArraySchema,
  type StringSchema,
  array,
  boolean,
  mixed,
  number,
  object,
  string
} from 'yup';
import {
  type FederatedWidgetOption,
  FederatedWidgetOptionType
} from '../../../../../../federatedModules/models';
import {
  labelPleaseSelectAMetric,
  labelPleaseSelectAResource,
  labelRequired
} from '../../../translatedLabels';
import {
  type ShowInput,
  type WidgetDataResource,
  WidgetResourceType
} from '../../models';

export const getProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['options', ...split('.', propertyName)], obj);

export const getDataProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['data', ...split('.', propertyName)], obj);

const namedEntitySchema = object().shape({
  id: mixed().required(),
  name: string().required()
});

const metricSchema = object().shape({
  id: number().required(),
  name: string().required(),
  unit: string()
});

interface GetYupValidatorTypeProps {
  properties: Pick<FederatedWidgetOption, 'defaultValue' | 'type'>;
  t: TFunction;
}

const getYupValidatorType = ({
  t,
  properties
}: GetYupValidatorTypeProps):
  | StringSchema
  | AnyObjectSchema
  | ArraySchema<AnySchema> =>
  cond<
    Array<FederatedWidgetOptionType>,
    StringSchema | AnyObjectSchema | ArraySchema<AnySchema>
  >([
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.textfield),
      always(string())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.richText),
      always(string())
    ],
    [
      equals<FederatedWidgetOptionType>(
        FederatedWidgetOptionType.singleMetricGraphType
      ),
      always(string())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.valueFormat),
      always(string())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.slider),
      always(number())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.resources),
      always(
        array()
          .of(
            object()
              .shape({
                resourceType:
                  properties.required || properties.requireResourceType
                    ? string().required(t(labelRequired) as string)
                    : string(),
                resources: properties.required
                  ? array().of(namedEntitySchema).min(1)
                  : array()
              })
              .optional()
          )
          .min(
            properties.required ? 1 : 0,
            t(labelPleaseSelectAResource) as string
          )
      )
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.metrics),
      always(
        array()
          .of(
            object()
              .shape({
                id: number().required(t(labelRequired) as string),
                metrics: array().of(metricSchema).min(1),
                name: string().required(t(labelRequired) as string)
              })
              .optional()
          )
          .when('resources', ([resources], schema) => {
            const hasMetaService = resources.some(({ resourceType }) =>
              equals(resourceType, WidgetResourceType.metaService)
            );

            if (hasMetaService) {
              return schema;
            }

            return schema.min(1, t(labelPleaseSelectAMetric) as string);
          })
      )
    ],
    [
      equals<FederatedWidgetOptionType>(
        FederatedWidgetOptionType.refreshInterval
      ),
      always(string())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.threshold),
      always(
        object().shape({
          critical: number().nullable(),
          enabled: boolean(),
          warning: number().nullable()
        })
      )
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.tiles),
      always(number().min(1))
    ]
  ])(properties.type);

interface BuildValidationSchemaProps {
  properties: Pick<FederatedWidgetOption, 'defaultValue' | 'type'>;
  t: TFunction;
}

export const buildValidationSchema = ({
  t,
  properties
}: BuildValidationSchemaProps): StringSchema => {
  const yupValidator = getYupValidatorType({
    properties,
    t
  });

  return properties.required
    ? yupValidator.required(t(labelRequired) as string)
    : yupValidator;
};

export const isAtLeastOneResourceFullfilled = (
  value: Array<WidgetDataResource>
): boolean =>
  value?.some(
    ({ resourceType, resources }) =>
      !isEmpty(resourceType) && !isEmpty(resources)
  );

export const resourceTypeQueryParameter = {
  [WidgetResourceType.host]: 'host.id',
  [WidgetResourceType.hostCategory]: 'hostcategory.id',
  [WidgetResourceType.hostGroup]: 'hostgroup.id',
  [WidgetResourceType.serviceCategory]: 'servicecategory.id',
  [WidgetResourceType.serviceGroup]: 'servicegroup.id',
  [WidgetResourceType.service]: 'service.name'
};

interface ShowInputProps extends ShowInput {
  values: FormikValues;
}

export const showInput = ({
  when,
  contains,
  notContains,
  values
}: ShowInputProps): boolean => {
  const dependencyValue = path(when.split('.'), values) as Array<object>;

  if (notContains) {
    return notContains.some(
      ({ key, value }) =>
        !includes(value, pluck(key, dependencyValue).join(','))
    );
  }

  if (contains) {
    return contains.some(({ key, value }) =>
      includes(value, pluck(key, dependencyValue).join(','))
    );
  }

  return true;
};

export const areResourcesFullfilled = (
  value: Array<WidgetDataResource> = []
): boolean =>
  value.every(
    ({ resourceType, resources }) =>
      !isEmpty(resourceType) && !isEmpty(resources)
  );
