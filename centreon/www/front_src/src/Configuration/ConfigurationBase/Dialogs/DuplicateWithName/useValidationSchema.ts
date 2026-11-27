import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { type ObjectSchema, object, string } from 'yup';

import { labelRequired } from '../../translatedLabels';
import type { DuplicateWithNameValues } from './useDuplicateWithName';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<DuplicateWithNameValues>;
}

const useValidationSchema = (): UseValidationSchemaState => {
  const { t } = useTranslation();

  const validationSchema = useMemo(
    () => object({ name: string().trim().required(t(labelRequired)) }),
    [t]
  );

  return { validationSchema };
};

export default useValidationSchema;
