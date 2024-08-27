import { useAtomValue } from 'jotai';
import { map, prop } from 'ramda';
import { useTranslation } from 'react-i18next';
import { ObjectSchema, ObjectShape, object, string } from 'yup';

import { resourceAccessRulesNamesAtom } from '../../atom';
import { labelNameAlreadyExists, labelRequired } from '../../translatedLabels';

interface UseValidateNameState {
  validationSchema: ObjectSchema<ObjectShape>;
}

const useValidateName = (): UseValidateNameState => {
  const { t } = useTranslation();
  const ruleNames = useAtomValue(resourceAccessRulesNamesAtom);

  const names = map(prop('name'), ruleNames);

  const validationSchema = object().shape({
    name: string()
      .required(t(labelRequired) as string)
      .notOneOf(names, t(labelNameAlreadyExists) as string)
  });

  return { validationSchema };
};

export default useValidateName;
