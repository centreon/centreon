import { number, object, string } from 'yup';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { TokenType } from '../../models';
import { labelRequired } from '../../translatedLabels';

const useValidationSchema = () => {
  const { t } = useTranslation();

  const validationSchema = object({
    duration: object({
      id: string().required(),
      name: string().required()
    }).required(t(labelRequired)),
    name: string().required(),
    type: object({
      id: string().required(),
      name: string().required()
    }).required(t(labelRequired)),
    user: object().when('type', ([type], schema) => {
      return equals(type.id, TokenType.API)
        ? schema
            .shape({
              id: number().required(),
              name: string().required()
            })
            .required(t(labelRequired))
        : schema.nullable();
    })
  });

  return {
    validationSchema
  };
};

export default useValidationSchema;
