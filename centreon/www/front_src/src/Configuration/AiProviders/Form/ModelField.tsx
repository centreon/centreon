import { Autocomplete, TextField } from '@mui/material';

import type { InputPropsWithoutGroup } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals, path } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelDefaultModelPlaceholder,
  labelFastModelPlaceholder
} from '../translatedLabels';
import { discoveredModelsAtom } from './discoveredModelsAtom';

interface FormValues {
  type?: string | null;
}

// the default model is the only required model field
const isRequired = (fieldName: string): boolean =>
  equals(fieldName, 'defaultModel');

const placeholder = (fieldName: string): string =>
  equals(fieldName, 'defaultModel')
    ? labelDefaultModelPlaceholder
    : labelFastModelPlaceholder;

const ModelField = ({
  fieldName,
  label
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { values, errors, touched, setFieldValue, setFieldTouched } =
    useFormikContext<FormValues>();
  const discovered = useAtomValue(discoveredModelsAtom);

  const value = (path([fieldName], values) as string | null) ?? '';
  const error = path([fieldName], touched)
    ? (path([fieldName], errors) as string | undefined)
    : undefined;

  // only offer discovered models that belong to the currently selected type
  const options = equals(discovered.type, values.type ?? null)
    ? discovered.models
    : [];

  return (
    <Autocomplete
      autoHighlight
      freeSolo
      inputValue={value}
      onBlur={() => setFieldTouched(fieldName, true, true)}
      onInputChange={(_event, newInput) => setFieldValue(fieldName, newInput)}
      options={options}
      renderInput={(params) => (
        <TextField
          {...params}
          error={Boolean(error)}
          helperText={error}
          inputProps={{ ...params.inputProps, 'data-testid': t(label) }}
          label={t(label)}
          placeholder={t(placeholder(fieldName))}
          required={isRequired(fieldName)}
          size="small"
        />
      )}
      value={value || null}
    />
  );
};

export default ModelField;
