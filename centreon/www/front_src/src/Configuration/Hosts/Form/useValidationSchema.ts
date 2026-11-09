import { useTranslation } from 'react-i18next';
import { type ObjectSchema, object } from 'yup';

import { getSchemaShape } from '../../ResourceForm/blocks';
import { blocks } from './sections';

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

  const validationSchema = object(getSchemaShape(blocks, context));

  return { validationSchema };
};

export default useValidationSchema;
