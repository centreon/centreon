import { useAtomValue } from 'jotai';
import { and, isEmpty, isNil, or } from 'ramda';
import { useTranslation } from 'react-i18next';
import Yup, {
  ObjectSchema,
  ObjectShape,
  array,
  object,
  string,
  number
} from 'yup';

import { notificationsNamesAtom } from '../../atom';
import { TimeperiodType } from '../../models';
import {
  labelChooseAtLeastOneResource,
  labelChooseAtleastOneContact,
  labelMessageFieldShouldNotBeEmpty,
  labelRequired,
  labelThisNameAlreadyExists,
  labelTimePeriodFieldShouldNotBeEmpty
} from '../../translatedLabels';
import { editedNotificationIdAtom } from '../atom';
import { NotificationType } from '../models';
import { emptyEmail } from '../utils';

interface UseValidationSchemaState {
  validationSchema: Yup.Schema<NotificationType>;
}

const useValidationSchema = ({
  isBamModuleInstalled
}: {
  isBamModuleInstalled?: boolean;
}): UseValidationSchemaState => {
  const { t } = useTranslation();
  const notificationsNames = useAtomValue(notificationsNamesAtom);
  const notificationId = useAtomValue(editedNotificationIdAtom);

  const names = notificationsNames
    .filter((item) => item.id !== notificationId)
    .map((item) => item.name);

  const validateName = string()
    .required(t(labelRequired) as string)
    .notOneOf(names, t(labelThisNameAlreadyExists) as string);

  const messagesSchema = object({
    message: string().notOneOf(
      [emptyEmail],
      t(labelMessageFieldShouldNotBeEmpty) as string
    ),
    subject: string().required(t(labelRequired) as string)
  });

  const timeperiodSchema: Yup.Schema<TimeperiodType> = object({
    id: number().required(t(labelRequired)),
    name: string().required(t(labelRequired))
  })
    .nullable()
    .required(t(labelTimePeriodFieldShouldNotBeEmpty));

  const resourceSchema = (
    dependency1: string,
    dependency2: string
  ): ObjectSchema<ObjectShape> =>
    object().when([dependency1, dependency2], ([value1, value2]) => {
      if (
        and(
          or(isNil(value1), isEmpty(value1)),
          or(isNil(value2), isEmpty(value2))
        )
      ) {
        return object().shape({
          ids: array().min(1, t(labelChooseAtLeastOneResource) as string)
        });
      }

      return object().shape({
        ids: array()
      });
    });

  const validationSchema = object().shape(
    {
      hostGroups: resourceSchema('serviceGroups.ids', 'businessviews.ids'),
      messages: messagesSchema,
      name: validateName,
      serviceGroups: resourceSchema('hostGroups.ids', 'businessviews.ids'),
      timeperiod: timeperiodSchema,
      users: array().min(1, t(labelChooseAtleastOneContact) as string),
      ...(isBamModuleInstalled
        ? {
            businessviews: resourceSchema('hostGroups.ids', 'serviceGroups.ids')
          }
        : {})
    },
    [
      ['hostGroups', 'serviceGroups'],
      ['hostGroups', 'businessviews'],
      ['serviceGroups', 'businessviews']
    ]
  );

  return { validationSchema };
};

export default useValidationSchema;
