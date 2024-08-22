import { useAtomValue } from 'jotai';
import { map, prop } from 'ramda';
import { useTranslation } from 'react-i18next';
import { ObjectSchema, ObjectShape, object, string } from 'yup';

import { notificationsNamesAtom } from '../../atom';
import {
  labelRequired,
  labelThisNameAlreadyExists
} from '../../translatedLabels';

const useValidateName = (): {
  validationSchema: ObjectSchema<ObjectShape>;
} => {
  const { t } = useTranslation();
  const notificationsNames = useAtomValue(notificationsNamesAtom);

  const names = map(prop('name'), notificationsNames);

  const validationSchema = object().shape({
    name: string()
      .required(t(labelRequired) as string)
      .notOneOf(names, t(labelThisNameAlreadyExists) as string)
  });

  return { validationSchema };
};

export default useValidateName;
