import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { ObjectSchema, object, string } from 'yup';

import { modalStateAtom } from '../../ConfigurationBase/atoms';
import { AiProviderType } from '../models';
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
    // the endpoint is only exposed for OpenAI-compatible providers:
    // the API enforces the official endpoint of the other types
    endpointUrl: string().when('type', ([type], schema) =>
      equals(type, AiProviderType.OpenAiCompatible)
        ? schema
            .matches(/^https?:\/\/.+/, t(labelInvalidUrl))
            .required(t(labelRequired))
        : schema.nullable()
    ),
    name: string().required(t(labelRequired)),
    type: string().required(t(labelRequired))
  });
};
