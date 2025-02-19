import { useTranslation } from 'react-i18next';
import { object, string } from 'yup';

import { labelName, labelRequired } from '../translatedLabels';

const useValidationSchema = (): { validationSchema } => {
  const { t } = useTranslation();

  const validationSchema = object({
    name: string()
      .label(t(labelName))
      // .min(3, t(labelNameMustBeAtLeast))
      // .max(50, t(labelNameMustBeMost))
      .required(t(labelRequired))
    // commet: string()
    // .label(t(labelComment))
    // .max(255, t(labelNameMustBeMost)),
    // alias: string()
    // .label(t(labelDescription) || '')
    // .max(180, t(labelDescriptionMustBeMost))
    // .nullable(),
  });

  return {
    validationSchema
  };
};

export default useValidationSchema;
