import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { ObjectSchema, object, string } from 'yup';

import { modalStateAtom } from '../../ConfigurationBase/atoms';
import { labelInvalidUrl, labelRequired } from '../translatedLabels';

export const useValidationSchema = (): ObjectSchema<object> => {
  const { t } = useTranslation();

  const { mode } = useAtomValue(modalStateAtom);
  const isEditing = equals(mode, 'edit');

  return object({
    apiToken: isEditing
      ? string().nullable()
      : string().required(t(labelRequired)),
    defaultModel: string().required(t(labelRequired)),
    endpointUrl: string()
      .matches(/^https?:\/\/.+/, t(labelInvalidUrl))
      .required(t(labelRequired)),
    name: string().required(t(labelRequired)),
    type: string().required(t(labelRequired))
  });
};
