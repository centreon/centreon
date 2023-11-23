import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';
import { ObjectSchema, ObjectShape, object, string } from 'yup';

import { resourceAccessRulesNamesAtom } from '../../atom';
import { editedResourceAccessRuleIdAtom } from '../atom';
import { labelNameAlreadyExists, labelRequired } from '../../translatedLabels';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<ObjectShape>;
}

const useValidationSchema = (): UseValidationSchemaState => {
  const { t } = useTranslation();
  const resourceAccessRulesNames = useAtomValue(resourceAccessRulesNamesAtom);
  const resourceAccessRuleId = useAtomValue(editedResourceAccessRuleIdAtom);

  const names = resourceAccessRulesNames
    .filter((item) => item.id !== resourceAccessRuleId)
    .map((item) => item.name);

  const validationName = string()
    .required(t(labelRequired) as string)
    .notOneOf(names, t(labelNameAlreadyExists) as string);

  const validationSchema = object().shape({ name: validationName });

  return { validationSchema };
};

export default useValidationSchema;
