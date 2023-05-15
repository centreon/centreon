import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
import * as Yup from 'yup';

import {
  labelRequired,
  labelChooseAtLeastOneResource,
  labelChooseAtleastOneUser,
  labelMessageFieldShouldNotBeEmpty
} from '../translatedLabels';

import { emptyEmail } from './utils';

const useValidationSchema = (): object => {
  const { t } = useTranslation();

  const messagesSchema = Yup.object({
    message: Yup.string().notOneOf(
      [emptyEmail],
      t(labelMessageFieldShouldNotBeEmpty)
    ),
    subject: Yup.string().required(t(labelRequired))
  });

  const resoureceSchema = (dependency): object =>
    Yup.object().when(dependency, {
      is: (value) => isEmpty(value),
      otherwise: Yup.object().shape({
        ids: Yup.array()
      }),
      then: Yup.object().shape({
        ids: Yup.array().min(1, t(labelChooseAtLeastOneResource))
      })
    });

  const validationSchema = Yup.object().shape(
    {
      hostGroups: resoureceSchema('serviceGroups.ids'),
      messages: messagesSchema,
      name: Yup.string().required(t(labelRequired)),
      serviceGroups: resoureceSchema('hostGroups.ids'),
      users: Yup.array().min(1, t(labelChooseAtleastOneUser))
    },
    ['hostGroups', 'serviceGroups']
  );

  return { validationSchema };
};

export default useValidationSchema;
