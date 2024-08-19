import { TFunction } from 'i18next';
import { useAtomValue } from 'jotai';
import { path, isEmpty, keys, toPairs } from 'ramda';
import { useTranslation } from 'react-i18next';
import * as Yup from 'yup';

import {
  FederatedWidgetOption,
  FederatedWidgetProperties
} from '../../../../federatedModules/models';
import { labelRequired } from '../translatedLabels';

import { buildValidationSchema } from './WidgetProperties/Inputs/utils';
import { widgetPropertiesAtom } from './atoms';

interface GetPropertiesValidationSchemaProps {
  properties: FederatedWidgetProperties | null;
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
  const filteredProperties = (
    properties ? path(propertyType.split('.'), properties) : []
  ) as Array<{
    [key: string]: FederatedWidgetOption & {
      group?: string;
    };
  }>;

  return toPairs(filteredProperties).reduce(
    (acc, [name, inputProp]) => ({
      ...acc,
      [name]: buildValidationSchema({
        properties: inputProp,
        t
      })
    }),
    {}
  );
};

const useValidationSchema = (): {
  schema: Yup.SchemaOf<unknown>;
} => {
  const { t } = useTranslation();

  const widgetProperties = useAtomValue(widgetPropertiesAtom);

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

  const inputCategories = keys(widgetProperties?.categories || {});

  const widgetCategoriesValidationSchema = inputCategories.reduce(
    (acc, category) => {
      const hasGroups = !isEmpty(
        path(['categories', category, 'groups'], widgetProperties)
      );

      return {
        ...acc,
        ...getPropertiesValidationSchema({
          properties: widgetProperties,
          propertyType: `categories.${category}${hasGroups ? '.elements' : ''}`,
          t
        })
      };
    },
    {}
  );

  const requiredText = t(labelRequired) as string;

  const schema = Yup.object({
    data: Yup.object(widgetDataValidationSchema).nullable(),
    options: Yup.object({
      description: Yup.object().shape({
        content: Yup.string().nullable(),
        enabled: Yup.boolean().required(requiredText)
      }),
      name: Yup.string().nullable(),
      ...widgetOptionsValidationSchema,
      ...widgetCategoriesValidationSchema
    })
  });

  return {
    schema
  };
};

export default useValidationSchema;
