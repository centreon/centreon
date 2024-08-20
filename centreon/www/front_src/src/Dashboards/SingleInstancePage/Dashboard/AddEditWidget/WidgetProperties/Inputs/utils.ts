import {
  always,
  cond,
  equals,
  includes,
  isEmpty,
  path,
  pluck,
  split
} from 'ramda';
import * as Yup from 'yup';
import { TFunction } from 'i18next';
import { FormikValues } from 'formik';

import {
  FederatedWidgetOption,
  FederatedWidgetOptionType
} from '../../../../../../federatedModules/models';
import {
  labelPleaseSelectAMetric,
  labelPleaseSelectAResource,
  labelRequired
} from '../../../translatedLabels';
import {
  ShowInput,
  WidgetDataResource,
  WidgetResourceType
} from '../../models';

export const getProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['options', ...split('.', propertyName)], obj);

export const getDataProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['data', ...split('.', propertyName)], obj);

const namedEntitySchema = Yup.object().shape({
  id: Yup.mixed().required(),
  name: Yup.string().required()
});

const metricSchema = Yup.object().shape({
  id: Yup.number().required(),
  name: Yup.string().required(),
  unit: Yup.string()
});

interface GetYupValidatorTypeProps {
  properties: Pick<FederatedWidgetOption, 'defaultValue' | 'type'>;
  t: TFunction;
}

const getYupValidatorType = ({
  t,
  properties
}: GetYupValidatorTypeProps):
  | Yup.StringSchema
  | Yup.AnyObjectSchema
  | Yup.ArraySchema<Yup.AnySchema> =>
  cond<
    Array<FederatedWidgetOptionType>,
    Yup.StringSchema | Yup.AnyObjectSchema | Yup.ArraySchema<Yup.AnySchema>
  >([
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.textfield),
      always(Yup.string())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.richText),
      always(Yup.string())
    ],
    [
      equals<FederatedWidgetOptionType>(
        FederatedWidgetOptionType.singleMetricGraphType
      ),
      always(Yup.string())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.valueFormat),
      always(Yup.string())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.slider),
      always(Yup.number())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.resources),
      always(
        Yup.array()
          .of(
            Yup.object()
              .shape({
                resourceType:
                  properties.required || properties.requireResourceType
                    ? Yup.string().required(t(labelRequired) as string)
                    : Yup.string(),
                resources: properties.required
                  ? Yup.array().of(namedEntitySchema).min(1)
                  : Yup.array()
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
        Yup.array()
          .of(
            Yup.object()
              .shape({
                id: Yup.number().required(t(labelRequired) as string),
                metrics: Yup.array().of(metricSchema).min(1),
                name: Yup.string().required(t(labelRequired) as string)
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
      always(Yup.string())
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.threshold),
      always(
        Yup.object().shape({
          critical: Yup.number().nullable(),
          enabled: Yup.boolean(),
          warning: Yup.number().nullable()
        })
      )
    ],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.tiles),
      always(Yup.number().min(1))
    ]
  ])(properties.type);

interface BuildValidationSchemaProps {
  properties: Pick<FederatedWidgetOption, 'defaultValue' | 'type'>;
  t: TFunction;
}

export const buildValidationSchema = ({
  t,
  properties
}: BuildValidationSchemaProps): Yup.StringSchema => {
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
