import { useTranslation } from 'react-i18next';
import * as Yup from 'yup';

import { labelRequired } from '../translatedLabels';

const useValidationSchema = (): object => {
  const { t } = useTranslation();

  const messagesSchema = Yup.object({
    message: Yup.string().required(t(labelRequired) as string),
    subject: Yup.string().required(t(labelRequired) as string)
  });

  const validationSchema = Yup.object().shape({
    messages: messagesSchema,
    name: Yup.string().required(t(labelRequired) as string)
  });

  return { validationSchema };
};

export default useValidationSchema;
