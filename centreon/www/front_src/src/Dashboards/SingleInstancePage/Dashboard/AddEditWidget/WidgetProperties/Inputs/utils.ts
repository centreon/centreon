import { ResourceType } from '@centreon/ui';
import type { FormikValues } from 'formik';
import type { TFunction } from 'i18next';
import {
  path,
  always,
  cond,
  equals,
  includes,
  isEmpty,
  isNil,
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
  type WidgetPropertyProps,
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
  properties: Pick<FederatedWidgetOption, 'defaultValue' | 'type'> &
    Pick<WidgetPropertyProps, 'isRequiredProperty' | 'isSingleAutocomplete'>;
  t: TFunction;
}

const isPropertyHidden = (properties, parentValues): boolean => {
  const { hiddenCondition } = properties;

  if (!hiddenCondition) {
    return false;
  }

  const conditions = Array.isArray(hiddenCondition)
    ? hiddenCondition
    : [hiddenCondition];

  return conditions.some(({ target, method, when, matches }) => {
    const values = { [target]: parentValues };

    if (equals(method, 'isNil')) {
      const formValue = path(when.split('.'), values);

      return isEmpty(formValue) || isNil(formValue);
    }

    return equals(path(when.split('.'), values), matches);
  });
};

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
            properties.required || properties?.requireResourceType ? 1 : 0,
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
            const hasMetaService = resources?.some(({ resourceType }) =>
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
    ],
    [
      equals<FederatedWidgetOptionType>(
        FederatedWidgetOptionType.connectedAutocomplete
      ),
      always(
        (properties.isSingleAutocomplete ? object() : array()).test(
          'connected-autocomplete-required',
          t(labelRequired) as string,
          (value, context) => {
            if (!(properties.required || properties.isRequiredProperty)) {
              return true;
            }

            if (isPropertyHidden(properties, context.parent)) {
              return true;
            }

            return !(isNil(value) || isEmpty(value));
          }
        )
      )
    ]
  ])(properties.type);

interface BuildValidationSchemaProps {
  properties: Pick<FederatedWidgetOption, 'defaultValue' | 'type'> &
    Pick<WidgetPropertyProps, 'isRequiredProperty' | 'isSingleAutocomplete'>;
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

  if (
    equals<FederatedWidgetOptionType>(
      properties.type,
      FederatedWidgetOptionType.connectedAutocomplete
    )
  ) {
    return yupValidator;
  }

  return properties.required || properties.isRequiredProperty
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
    return notContains?.some(
      ({ key, value }) =>
        !includes(value, pluck(key, dependencyValue).join(','))
    );
  }

  if (contains) {
    return contains?.some(({ key, value }) =>
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

export const getIsMetaServiceSelected = (
  resources: Array<WidgetDataResource> = []
): boolean =>
  equals(resources.length, 1) &&
  equals(resources[0].resourceType, ResourceType.metaService);
