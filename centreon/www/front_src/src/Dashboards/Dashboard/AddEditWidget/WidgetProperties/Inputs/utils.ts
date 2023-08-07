import { always, cond, equals, path } from 'ramda';
import * as Yup from 'yup';
import { TFunction } from 'i18next';

import { FederatedWidgetOptionType } from '../../../../../federatedModules/models';
import { labelRequired } from '../../../translatedLabels';

export const getProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['options', propertyName], obj);

const getYupValidatorType = cond([
  [equals(FederatedWidgetOptionType.textfield), always(Yup.string())],
  [equals(FederatedWidgetOptionType.richText), always(Yup.string())]
]);

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
  const yupValidator = getYupValidatorType(type);

  return required
    ? yupValidator.required(t(labelRequired) as string)
    : yupValidator;
};
