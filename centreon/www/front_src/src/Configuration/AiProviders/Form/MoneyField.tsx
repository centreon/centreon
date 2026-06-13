import { TextField } from '@mui/material';

import type { InputPropsWithoutGroup } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { path } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

// the shared number Text input parses with parseInt, which forbids decimals;
// this field keeps the typed string so prices/limits like 0.01 can be entered
const MoneyField = ({
  fieldName,
  label
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { values, errors, touched, setFieldValue, setFieldTouched } =
    useFormikContext();

  const fieldPath = fieldName.split('.');
  const value = path(fieldPath, values);
  const error = path(fieldPath, touched) ? path(fieldPath, errors) : undefined;

  return (
    <TextField
      error={Boolean(error)}
      fullWidth
      helperText={error as string | undefined}
      inputProps={{ 'data-testid': t(label), min: 0, step: 'any' }}
      label={t(label)}
      onBlur={() => setFieldTouched(fieldName, true, true)}
      onChange={(event) =>
        setFieldValue(
          fieldName,
          event.target.value === '' ? null : event.target.value
        )
      }
      size="small"
      type="number"
      value={value ?? ''}
    />
  );
};

export default MoneyField;
