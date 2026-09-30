import { useTranslation } from 'react-i18next';
import { number, type ObjectSchema, object, string } from 'yup';

import {
  labelInvalidAddress,
  labelNameContainsForbiddenCharacters,
  labelNameMustNotStartWithModule,
  labelRequired
} from '../translatedLabels';

// Uniqueness is not checked here: the server owns it, and a client check goes
// stale the moment someone else creates a host.
const nameMaxLength = 200;
const addressMaxLength = 255;
const forbiddenNameCharacters = /^[^~!$%^&*"\\|'<>?,()=]*$/;
const moduleNamePrefix = /^_Module_/;

// An IPv4 or IPv6 address, or a name the poller can resolve.
const address =
  /^(\d{1,3}(\.\d{1,3}){3}|[\da-fA-F:]+:[\da-fA-F:.]*|[a-zA-Z\d]([a-zA-Z\d-]*[a-zA-Z\d])?(\.[a-zA-Z\d]([a-zA-Z\d-]*[a-zA-Z\d])?)*)$/;

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<object>;
}

const useValidationSchema = (): UseValidationSchemaState => {
  const { t } = useTranslation();

  const validationSchema = object({
    address: string()
      .max(addressMaxLength)
      .matches(address, t(labelInvalidAddress))
      .required(t(labelRequired)),
    name: string()
      .max(nameMaxLength)
      .matches(forbiddenNameCharacters, t(labelNameContainsForbiddenCharacters))
      .test(
        'is-not-a-module',
        t(labelNameMustNotStartWithModule),
        (value) => !moduleNamePrefix.test(value ?? '')
      )
      .required(t(labelRequired)),
    poller: object({
      id: number().required(t(labelRequired)),
      name: string()
    })
      .nullable()
      .required(t(labelRequired))
  });

  return { validationSchema };
};

export default useValidationSchema;
