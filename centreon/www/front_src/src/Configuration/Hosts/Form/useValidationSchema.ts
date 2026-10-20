import { mergeAll } from 'ramda';
import { useTranslation } from 'react-i18next';
import { type ObjectSchema, object } from 'yup';

import { getAvailableSections } from './sections';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<object>;
}

const useValidationSchema = ({
  isCloudPlatform,
  isMassChange = false
}: {
  isCloudPlatform: boolean;
  isMassChange?: boolean;
}): UseValidationSchemaState => {
  const { t } = useTranslation();

  const context = { isCloudPlatform, isMassChange, t };

  const validationSchema = object(
    mergeAll(
      getAvailableSections(context).map(({ section }) =>
        section.getSchema(context)
      )
    )
  );

  return { validationSchema };
};

export default useValidationSchema;
