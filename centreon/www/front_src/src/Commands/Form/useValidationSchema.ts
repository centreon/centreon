import { useTranslation } from 'react-i18next';
import { Schema, object, string } from 'yup';

import { labelRequired } from '../translatedLabels';

export const useValidationSchema = (): Schema => {
  const { t } = useTranslation();

  return object({
    name: string().required(t(labelRequired))
    // type: mixed().required(t(labelRequired))
  });
};
