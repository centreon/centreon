import { and, isEmpty, isNil, or } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';
import {
  AnySchema,
  ArraySchema,
  ObjectSchema,
  ObjectShape,
  array,
  object,
  string
} from 'yup';

import {
  labelRequired,
  labelChooseAtLeastOneResource,
  labelChooseAtleastOneContactOrContactGroup,
  labelMessageFieldShouldNotBeEmpty,
  labelThisNameAlreadyExists
} from '../../translatedLabels';
import { notificationsNamesAtom } from '../../atom';
import { emptyEmail } from '../utils';
import { editedNotificationIdAtom } from '../atom';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<ObjectShape>;
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

  const resourceSchema = (
    dependency1,
    dependency2
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

  const contactsSchema = (dependency): ArraySchema<AnySchema> =>
    array().when(dependency, ([value]) => {
      if (isEmpty(value)) {
        return array().min(
          1,
          t(labelChooseAtleastOneContactOrContactGroup) as string
        );
      }

      return array();
    });

  const validationSchema = object().shape(
    {
      contactgroups: contactsSchema('users'),
      hostGroups: resourceSchema('serviceGroups.ids', 'businessviews.ids'),
      messages: messagesSchema,
      name: validateName,
      serviceGroups: resourceSchema('hostGroups.ids', 'businessviews.ids'),
      users: contactsSchema('contactgroups'),
      ...(isBamModuleInstalled
        ? {
            businessviews: resourceSchema('hostGroups.ids', 'serviceGroups.ids')
          }
        : {})
    },
    [
      ['users', 'contactgroups'],
      ['hostGroups', 'serviceGroups'],
      ['hostGroups', 'businessviews'],
      ['serviceGroups', 'businessviews']
    ]
  );

  return { validationSchema };
};

export default useValidationSchema;
