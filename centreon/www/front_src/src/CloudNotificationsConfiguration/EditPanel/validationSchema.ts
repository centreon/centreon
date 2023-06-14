import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
import * as Yup from 'yup';
import { useAtomValue } from 'jotai';

import {
  labelRequired,
  labelChooseAtLeastOneResource,
  labelChooseAtleastOneUser,
  labelMessageFieldShouldNotBeEmpty,
  labelThisNameAlreadyExists
} from '../translatedLabels';
import { notificationsNamesAtom } from '../atom';

import { emptyEmail } from './utils';

const useValidationSchema = (): object => {
  const { t } = useTranslation();
  const notificationsNames = useAtomValue(notificationsNamesAtom);

  const messagesSchema = Yup.object({
    message: Yup.string().notOneOf(
      [emptyEmail],
      t(labelMessageFieldShouldNotBeEmpty) as string
    ),
    subject: Yup.string().required(t(labelRequired) as string)
  });

  const resourceSchema = (dependency): object =>
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
      hostGroups: resourceSchema('serviceGroups.ids'),
      messages: messagesSchema,
      name: Yup.string()
        .required(t(labelRequired) as string)
        .notOneOf(notificationsNames, t(labelThisNameAlreadyExists) as string),
      serviceGroups: resourceSchema('hostGroups.ids'),
      users: Yup.array().min(1, t(labelChooseAtleastOneUser) as string)
    },
    ['hostGroups', 'serviceGroups']
  );

  return { validationSchema };
};

export default useValidationSchema;
