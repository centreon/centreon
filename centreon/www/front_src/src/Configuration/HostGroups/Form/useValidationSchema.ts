import { platformFeaturesAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { array, number, ObjectSchema, object, string } from 'yup';

import {
  labelInvalidCoordinateFormat,
  labelName,
  labelRequired
} from '../translatedLabels';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<{
    name: string;
    geoCoords?: string;
    resourceAccessRules?: Array<{ id: number; name: string }>;
  }>;
}

const useValidationSchema = (): UseValidationSchemaState => {
  const { t } = useTranslation();

  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const isCloudPlatform = platformFeatures?.isCloudPlatform;

  const selectEntryValidationSchema = object().shape({
    id: number().required(t(labelRequired)),
    name: string().required(t(labelRequired))
  });

  const validationSchema = object({
    geoCoords: string()
      .matches(
        /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/,
        t(labelInvalidCoordinateFormat)
      )
      .nullable(),
    name: string().label(t(labelName)).required(t(labelRequired)),
    resourceAccessRules: array()
      .of(selectEntryValidationSchema)
      .when([], {
        is: () => isCloudPlatform,
        otherwise: (schema) => schema.optional(),
        // biome-ignore lint/suspicious/noThenProperty: <explanation>
        then: (schema) => schema.min(1, t(labelRequired))
      })
  });

  return {
    validationSchema
  };
};

export default useValidationSchema;
