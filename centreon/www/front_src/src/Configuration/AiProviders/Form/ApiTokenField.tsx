import type { InputPropsWithoutGroup } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals, path } from 'ramda';
import { type ChangeEvent, JSX, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { formStateAtom } from '../../ConfigurationBase/atoms';
import StoredSecretField from '../../ConfigurationBase/Form/Macros/StoredSecretField';
import { labelApiToken } from '../translatedLabels';

// The API never returns a stored token (write-only). In edit mode the field
// therefore starts masked to signal that a token is configured; clearing it
// lets the user type a replacement, and reverting keeps the stored one (an
// empty value tells the API to keep the current token).
const ApiTokenField = ({ fieldName }: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { values, errors, touched, setFieldValue, setFieldTouched } =
    useFormikContext();

  const { mode } = useAtomValue(formStateAtom);
  const isEditing = equals(mode, 'edit');

  const [isErased, setIsErased] = useState(false);

  const value = (path([fieldName], values) as string | null) ?? '';
  const error = path([fieldName], touched)
    ? (path([fieldName], errors) as string | undefined)
    : undefined;

  return (
    <StoredSecretField
      dataTestId={t(labelApiToken)}
      error={error}
      isErased={isErased}
      isStored={isEditing}
      label={t(labelApiToken)}
      onBlur={() => setFieldTouched(fieldName, true, true)}
      onChange={(event: ChangeEvent<HTMLInputElement>) =>
        setFieldValue(fieldName, event.target.value)
      }
      onErase={() => {
        setIsErased(true);
        setFieldValue(fieldName, '');
      }}
      onRevert={() => {
        setIsErased(false);
        setFieldValue(fieldName, '');
      }}
      type="password"
      value={value}
    />
  );
};

export default ApiTokenField;
