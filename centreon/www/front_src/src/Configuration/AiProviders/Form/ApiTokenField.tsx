import type { InputPropsWithoutGroup } from '@centreon/ui';

import { type FormikValues, useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals, path } from 'ramda';
import { type ChangeEvent, JSX, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import { formStateAtom } from '../../ConfigurationBase/atoms';
import StoredSecretField from '../../ConfigurationBase/Form/Macros/StoredSecretField';
import { AiProviderType } from '../models';
import {
  labelApiToken,
  labelApiTokenOptionalHint,
  labelApiTokenWillBeRemoved
} from '../translatedLabels';
import { isApiTokenOptional, isSameEndpoint } from '../utils';

// The API never returns a stored token (write-only). In edit mode the field
// therefore starts masked to signal that a token is configured; clearing it
// lets the user type a replacement, and reverting keeps the stored one (an
// empty value tells the API to keep the current token). The stored token is
// only sent to the endpoint it was configured for: once the endpoint changes,
// the field is a plain input again and the token has to be typed.
const ApiTokenField = ({
  fieldName,
  getDisabled
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const {
    values,
    initialValues,
    errors,
    touched,
    setFieldValue,
    setFieldTouched
  } = useFormikContext<FormikValues>();

  const { mode } = useAtomValue(formStateAtom);
  const isEditing = equals(mode, 'edit');

  // kept with the form values, so that resetting the form restores it too
  const isErased = Boolean(path(['apiTokenErased'], values));

  // a server that needs none may have no token stored
  const hasStoredToken =
    isEditing && path(['hasApiToken'], initialValues) !== false;
  const isStoredTokenUsable =
    hasStoredToken &&
    isSameEndpoint(
      path(['endpointUrl'], values) as string | null,
      path(['endpointUrl'], initialValues) as string | null
    );
  const isOptional = isApiTokenOptional(
    path(['type'], values) as AiProviderType | null
  );
  const isDisabled = getDisabled?.(values) ?? false;

  const value = (path([fieldName], values) as string | null) ?? '';
  // left empty once cleared, or once the endpoint changed: only a server that
  // may need no token can have its stored one removed, and never implicitly
  const willRemoveStoredToken =
    isOptional &&
    hasStoredToken &&
    value === '' &&
    (isErased || !isStoredTokenUsable);

  const removeApiToken = Boolean(path(['removeApiToken'], values));

  // what is saved always matches what the field says
  useEffect(() => {
    if (removeApiToken !== willRemoveStoredToken) {
      setFieldValue('removeApiToken', willRemoveStoredToken);
    }
  }, [willRemoveStoredToken, removeApiToken]);

  const getHelperText = (): string | undefined => {
    if (willRemoveStoredToken) {
      return t(labelApiTokenWillBeRemoved);
    }

    return isOptional && !isStoredTokenUsable
      ? t(labelApiTokenOptionalHint)
      : undefined;
  };

  // a changed endpoint explains right away why the token is needed again
  const error =
    path([fieldName], touched) || (isEditing && !isStoredTokenUsable)
      ? (path([fieldName], errors) as string | undefined)
      : undefined;

  return (
    <StoredSecretField
      dataTestId={t(labelApiToken)}
      disabled={isDisabled}
      error={error}
      helperText={getHelperText()}
      isErased={isErased}
      isStored={isStoredTokenUsable}
      label={t(labelApiToken)}
      onBlur={() => setFieldTouched(fieldName, true, true)}
      onChange={(event: ChangeEvent<HTMLInputElement>) =>
        setFieldValue(fieldName, event.target.value)
      }
      onErase={() => {
        setFieldValue('apiTokenErased', true);
        setFieldValue(fieldName, '');
      }}
      onRevert={() => {
        setFieldValue('apiTokenErased', false);
        setFieldValue(fieldName, '');
      }}
      required={!isOptional && !isDisabled}
      type="password"
      value={value}
    />
  );
};

export default ApiTokenField;
