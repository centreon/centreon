import { useTranslation } from 'react-i18next';
import { type ObjectSchema, object } from 'yup';

import { getSchemaShape } from '../../ResourceForm/blocks';
import { blocks } from './sections';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<object>;
}

const useValidationSchema = ({
  isCloudPlatform
}: {
  isCloudPlatform: boolean;
}): UseValidationSchemaState => {
  const { t } = useTranslation();

  const validationSchema = object(
    getSchemaShape(blocks, { isCloudPlatform, t })
  );

  return { validationSchema };
};

export default useValidationSchema;
