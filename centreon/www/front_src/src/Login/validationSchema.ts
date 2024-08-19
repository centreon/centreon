import { useTranslation } from 'react-i18next';
import { string, object } from 'yup';
import type { Schema } from 'yup';

import { LoginFormValues } from './models';
import { labelRequired } from './translatedLabels';

const useValidationSchema = (): Schema<LoginFormValues> => {
  const { t } = useTranslation();

  const schema = object().shape({
    alias: string().required(t(labelRequired)),
    password: string().required(t(labelRequired))
  });

  return schema;
};

export default useValidationSchema;
