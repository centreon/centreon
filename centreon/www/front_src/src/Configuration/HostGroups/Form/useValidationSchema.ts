import { useTranslation } from 'react-i18next';
import { ObjectSchema, object, string } from 'yup';

import {
  labelInvalidCoordinateFormat,
  labelName,
  labelRequired
} from '../translatedLabels';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<{
    name: string;
    geoCoords?: string;
  }>;
}

const useValidationSchema = (): UseValidationSchemaState => {
  const { t } = useTranslation();

  const validationSchema = object({
    name: string().label(t(labelName)).required(t(labelRequired)),
    geoCoords: string()
      .matches(
        /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/,
        t(labelInvalidCoordinateFormat)
      )
      .nullable()
  });

  return {
    validationSchema
  };
};

export default useValidationSchema;
