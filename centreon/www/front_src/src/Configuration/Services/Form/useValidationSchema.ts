import { mergeAll } from 'ramda';
import { useTranslation } from 'react-i18next';
import { type ObjectSchema, object } from 'yup';

import { getAvailableSections, type PlatformContext } from './sections';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<object>;
}

const useValidationSchema = ({
  isAdditiveInheritanceEnabled,
  isCloudPlatform,
  isMassChange = false
}: PlatformContext): UseValidationSchemaState => {
  const { t } = useTranslation();

  const context = {
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    isMassChange,
    t
  };

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
