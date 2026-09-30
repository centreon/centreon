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
// The set `CreateHostInput` forbids, character for character. A backslash is
// not among them, so the form must not refuse `C:\temp` either.
const forbiddenNameCharacters = /^[^~!$%^&*"|'<>?,()=]*$/;
// The server spells the reserved prefix `_Module_` or `_Module `.
const moduleNamePrefix = /^_Module[_ ]/;

// An IPv4 or IPv6 address, or a name the poller can resolve. The underscore is
// deliberate: `Assert::HOSTNAME_PATTERN` allows it for NetBIOS and Active
// Directory names, so `srv_01` must reach the API rather than stop here.
const address =
  /^(\d{1,3}(\.\d{1,3}){3}|[\da-fA-F:]+:[\da-fA-F:.]*|\w([\w-]*\w)?(\.\w([\w-]*\w)?)*)$/;

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<object>;
}

const useValidationSchema = (): UseValidationSchemaState => {
  const { t } = useTranslation();

  const validationSchema = object({
    address: string()
      .trim()
      .max(addressMaxLength)
      // Without this an empty address reports itself as invalid rather than
      // as missing, which the name field next to it does not do.
      .matches(address, {
        excludeEmptyString: true,
        message: t(labelInvalidAddress)
      })
      .required(t(labelRequired)),
    // Both fields are trimmed the way the server normalises them, so blanks
    // report as missing instead of passing to a 422.
    name: string()
      .trim()
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
