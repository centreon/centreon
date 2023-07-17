import * as Yup from 'yup';
import { ObjectShape } from 'yup/lib/object';
import { map, prop } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { notificationsNamesAtom } from '../../atom';
import {
  labelRequired,
  labelThisNameAlreadyExists
} from '../../translatedLabels';

const useValidateName = (): {
  validationSchema: Yup.ObjectSchema<ObjectShape>;
} => {
  const { t } = useTranslation();
  const notificationsNames = useAtomValue(notificationsNamesAtom);

  const names = map(prop('name'), notificationsNames);

  const validationSchema = Yup.object().shape({
    name: Yup.string()
      .required(t(labelRequired) as string)
      .notOneOf(names, t(labelThisNameAlreadyExists) as string)
  });

  return { validationSchema };
};

export default useValidateName;
