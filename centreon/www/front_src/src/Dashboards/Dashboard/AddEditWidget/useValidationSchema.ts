import { useTranslation } from 'react-i18next';
import * as Yup from 'yup';
import { useAtomValue } from 'jotai';

import { labelRequired } from '../translatedLabels';

import { WidgetPropertiesRenderer } from './WidgetProperties/useWidgetProperties';
import { buildValidationSchema } from './WidgetProperties/Inputs/utils';
import { widgetPropertiesAtom } from './atoms';

const useValidationSchema = (): {
  schema: Yup.SchemaOf<unknown>;
} => {
  const { t } = useTranslation();

  const widgetProperties = useAtomValue<Array<WidgetPropertiesRenderer> | null>(
    widgetPropertiesAtom
  );

  const widgetValidationSchema = (widgetProperties || []).reduce(
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

  const requiredText = t(labelRequired) as string;

  const schema = Yup.object({
    options: Yup.object({
      description: Yup.string().nullable(),
      name: Yup.string().required(requiredText),
      ...widgetValidationSchema
    })
  });

  return {
    schema
  };
};

export default useValidationSchema;
