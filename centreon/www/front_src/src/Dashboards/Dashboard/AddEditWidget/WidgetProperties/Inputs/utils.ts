import { always, cond, equals, path } from 'ramda';
import * as Yup from 'yup';
import { TFunction } from 'i18next';

import { FederatedWidgetOptionType } from '../../../../../federatedModules/models';
import {
  labelPleaseSelectAMetric,
  labelPleaseSelectAResource,
  labelRequired
} from '../../../translatedLabels';

export const getProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['options', propertyName], obj);

export const getDataProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['data', propertyName], obj);

const namedEntitySchema = Yup.object().shape({
  id: Yup.number().required(),
  name: Yup.string().required()
});

const metricSchema = Yup.object().shape({
  id: Yup.number().required(),
  name: Yup.string().required(),
  unit: Yup.string().required()
});

interface GetYupValidatorTypeProps {
  t: TFunction;
  widgetOptionType: FederatedWidgetOptionType;
}

const getYupValidatorType = ({
  t,
  widgetOptionType
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
    [equals(FederatedWidgetOptionType.richText), always(Yup.string())],
    [
      equals<FederatedWidgetOptionType>(FederatedWidgetOptionType.resources),
      always(
        Yup.array()
          .of(
            Yup.object()
              .shape({
                resourceType: Yup.string().required(t(labelRequired) as string),
                resources: Yup.array().of(namedEntitySchema).min(1)
              })
              .optional()
          )
          .min(1, t(labelPleaseSelectAResource) as string)
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
                metrics: Yup.array().of(metricSchema).min(1)
              })
              .optional()
          )
          .min(1, t(labelPleaseSelectAMetric) as string)
      )
    ]
  ])(widgetOptionType);

interface BuildValidationSchemaProps {
  required?: boolean;
  t: TFunction;
  type: FederatedWidgetOptionType;
}

export const buildValidationSchema = ({
  type,
  required,
  t
}: BuildValidationSchemaProps): Yup.StringSchema => {
  const yupValidator = getYupValidatorType({ t, widgetOptionType: type });

  return required
    ? yupValidator.required(t(labelRequired) as string)
    : yupValidator;
};
