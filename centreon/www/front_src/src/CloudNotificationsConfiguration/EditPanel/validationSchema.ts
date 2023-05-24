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
      t(labelMessageFieldShouldNotBeEmpty) as string
    ),
    subject: Yup.string().nullable()
  });

  const resoureceSchema = (dependency): object =>
    Yup.object().when(dependency, {
      is: (value) => isEmpty(value),
      otherwise: Yup.object().shape({
        ids: Yup.array()
      }),
      then: Yup.object().shape({
        ids: Yup.array().min(1, t(labelChooseAtLeastOneResource) as string)
      })
    });

  const validationSchema = Yup.object().shape(
    {
      hostGroups: resoureceSchema('serviceGroups.ids'),
      messages: messagesSchema,
      name: Yup.string().required(t(labelRequired) as string),
      serviceGroups: resoureceSchema('hostGroups.ids'),
      users: Yup.array().min(1, t(labelChooseAtleastOneUser) as string)
    },
    ['hostGroups', 'serviceGroups']
  );

  return { validationSchema };
};

export default useValidationSchema;
