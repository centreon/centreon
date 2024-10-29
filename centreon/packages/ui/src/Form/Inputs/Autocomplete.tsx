import { useCallback, useMemo, useState } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { equals, isNil, map, not, path, prop, type } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FormHelperText, Stack } from '@mui/material';

import { SelectEntry } from '../../InputField/Select';
import SingleAutocompleteField from '../../InputField/Select/Autocomplete';
import MultiAutocompleteField from '../../InputField/Select/Autocomplete/Multi';
import { useMemoComponent } from '../../utils';
import { labelPressEnterToAccept } from '../translatedLabels';

import { InputPropsWithoutGroup, InputType } from './models';

const normalizeNewValues = ({
  newValues,
  isMultiple,
  isCreatable
}): SelectEntry | Array<string | SelectEntry> => {
  const isSingle = not(isMultiple);
  if (isSingle) {
    return newValues;
  }

  return map((newValue: SelectEntry | string) => {
    const isManualValue = equals(type(newValue), 'String');
    if (isCreatable && isManualValue) {
      return newValue;
    }

    if (isCreatable) {
      return prop('name', newValue as SelectEntry);
    }

    return newValue;
  }, newValues);
};

const Autocomplete = ({
  fieldName,
  label,
  required,
  getDisabled,
  getRequired,
  change,
  additionalMemoProps,
  autocomplete,
  type: inputType
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const [inputText, setInputText] = useState('');

  const {
    values,
    setFieldValue,
    setFieldTouched,
    errors,
    touched,
    setValues,
    setTouched
  } = useFormikContext<FormikValues>();

  const isMultiple = equals(inputType, InputType.MultiAutocomplete);

  const changeValues = (_, newValues): void => {
    const normalizedNewValues = normalizeNewValues({
      isCreatable,
      isMultiple,
      newValues
    });

    setInputText('');

    if (change) {
      setFieldTouched(fieldName, true, false);
      change({
        setFieldValue,
        value: normalizedNewValues,
        setFieldTouched,
        setValues,
        values,
        setTouched
      });

      return;
    }

    setFieldTouched(fieldName, true, false);
    setFieldValue(fieldName, normalizedNewValues);
  };

  const isCreatable = autocomplete?.creatable;

  const selectedValues = path<Array<SelectEntry> | SelectEntry>(
    [...fieldName.split('.')],
    values
  );

  const getError = useCallback((): Array<string> | undefined => {
    if (!path([...fieldName.split('.')], touched)) {
      return undefined;
    }

    const error = path([...fieldName.split('.')], errors) as
      | Array<string>
      | string
      | undefined;

    const isStringError = equals(type(error), 'String');

    if (isMultiple && !isStringError) {
      const formattedErrors = (error as Array<string> | undefined)?.map(
        (errorText, index) => {
          if (isNil(errorText)) {
            return undefined;
          }

          return `${selectedValues?.[index]}: ${errorText}`;
        }
      );

      const filteredErrors = formattedErrors?.filter(Boolean);

      return (filteredErrors as Array<string>) || undefined;
    }

    const formattedError = [error];

    const filteredError = formattedError?.filter(Boolean);

    return (filteredError as Array<string>) || undefined;
  }, [errors, fieldName, isMultiple, selectedValues, touched]);

  const textChange = useCallback(
    (event): void => setInputText(event.target.value),
    []
  );

  const getValues = useCallback(():
    | SelectEntry
    | Array<SelectEntry>
    | undefined => {
    if (isMultiple && isCreatable) {
      return equals(type(selectedValues), 'Array')
        ? (
            selectedValues as Array<SelectEntry> | Array<string> | undefined
          )?.map((value) => ({
            id: value,
            name: value
          }))
        : selectedValues;
    }

    return selectedValues;
  }, [isMultiple, isCreatable, selectedValues]);

  const inputErrors = getError();

  const disabled = getDisabled?.(values) || false;
  const isRequired = required || getRequired?.(values) || false;

  const additionalLabel =
    inputText && isCreatable ? ` (${labelPressEnterToAccept})` : '';

  const AutocompleteField = useMemo(
    () => (isMultiple ? MultiAutocompleteField : SingleAutocompleteField),
    [isMultiple]
  );

  return useMemoComponent({
    Component: (
      <div>
        <AutocompleteField
          disabled={disabled}
          freeSolo={isCreatable}
          inputValue={inputText}
          isOptionEqualToValue={(option, selectedValue): boolean =>
            equals(option, selectedValue)
          }
          label={`${t(label)}${additionalLabel}`}
          open={isCreatable ? false : undefined}
          options={autocomplete?.options || []}
          popupIcon={isCreatable ? null : undefined}
          required={isRequired}
          value={getValues() ?? null}
          onChange={changeValues}
          onTextChange={textChange}
          style={{ width: autocomplete?.fullWidth ?? true ? 'auto' : '180px' }}
        />
        {inputErrors && (
          <Stack>
            {inputErrors.map((error) => (
              <FormHelperText error key={error}>
                {error}
              </FormHelperText>
            ))}
          </Stack>
        )}
      </div>
    ),
    memoProps: [
      values,
      getValues(),
      inputErrors,
      additionalLabel,
      disabled,
      additionalMemoProps,
      isMultiple,
      autocomplete?.options,
      isCreatable
    ]
  });
};

export default Autocomplete;
