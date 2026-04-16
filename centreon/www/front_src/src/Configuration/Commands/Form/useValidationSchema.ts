import { useTranslation } from 'react-i18next';
import { object, Schema, string } from 'yup';

import { labelRequired } from '../translatedLabels';

export const useValidationSchema = (): Schema => {
  const { t } = useTranslation();

  return object({
    commandLine: string().required(t(labelRequired)),
    name: string().required(t(labelRequired)),
    type: string().required(t(labelRequired))
  });
};
