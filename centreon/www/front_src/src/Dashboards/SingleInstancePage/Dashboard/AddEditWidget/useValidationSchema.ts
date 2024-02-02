import { useTranslation } from 'react-i18next';
import * as Yup from 'yup';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { TFunction } from 'i18next';

import { labelRequired } from '../translatedLabels';

import { WidgetPropertiesRenderer } from './WidgetProperties/useWidgetInputs';
import { buildValidationSchema } from './WidgetProperties/Inputs/utils';
import { widgetPropertiesAtom } from './atoms';

interface GetPropertiesValidationSchemaProps {
  properties: Array<WidgetPropertiesRenderer> | null;
  propertyType: 'options' | 'data';
  t: TFunction;
}

const getPropertiesValidationSchema = ({
  t,
  properties,
  propertyType
}: GetPropertiesValidationSchemaProps): Record<
  string,
  Yup.StringSchema<string | undefined, Yup.AnyObjectSchema, string | undefined>
> => {
  const filteredProperties = (properties || []).filter(({ props }) =>
    equals(props.propertyType, propertyType)
  );

  return filteredProperties.reduce(
    (acc, { props }) => ({
      ...acc,
      [props.propertyName]: buildValidationSchema({
        required: props.required,
        t,
        type: props.type
      })
    }),
    {}
  );
};

const useValidationSchema = (): {
  schema: Yup.SchemaOf<unknown>;
} => {
  const { t } = useTranslation();

  const widgetProperties = useAtomValue<Array<WidgetPropertiesRenderer> | null>(
    widgetPropertiesAtom
  );

  const widgetOptionsValidationSchema = getPropertiesValidationSchema({
    properties: widgetProperties,
    propertyType: 'options',
    t
  });

  const widgetDataValidationSchema = getPropertiesValidationSchema({
    properties: widgetProperties,
    propertyType: 'data',
    t
  });

  const requiredText = t(labelRequired) as string;

  const schema = Yup.object({
    data: Yup.object(widgetDataValidationSchema).nullable(),
    options: Yup.object({
      description: Yup.object().shape({
        content: Yup.string().nullable(),
        enabled: Yup.boolean().required(requiredText)
      }),
      name: Yup.string().nullable(),
      openLinksInNewTab: Yup.boolean().required(requiredText),
      ...widgetOptionsValidationSchema
    })
  });

  return {
    schema
  };
};

export default useValidationSchema;
