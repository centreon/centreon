import { FormikValues, useFormikContext } from 'formik';
import { equals, isEmpty, path, propEq, reject, split } from 'ramda';
import { useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import {
  buildListingEndpoint,
  SingleConnectedAutocompleteField,
  useMemoComponent
} from '../..';
import MultiConnectedAutocompleteField from '../../InputField/Select/Autocomplete/Connected/Multi';
import { InputPropsWithoutGroup, InputType } from './models';

const defaultFilterKey = 'name';

const ConnectedAutocomplete = ({
  dataTestId,
  getDisabled,
  required,
  getRequired,
  fieldName,
  label,
  connectedAutocomplete,
  change,
  additionalMemoProps,
  type,
  disableSortedOptions = false
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const {
    values,
    touched,
    errors,
    setFieldValue,
    setFieldTouched,
    setValues,
    setTouched
  } = useFormikContext<FormikValues>();

  const filterKey = connectedAutocomplete?.filterKey || defaultFilterKey;

  const isMultiple = equals(type, InputType.MultiConnectedAutocomplete);

  const getEndpoint = (parameters): string => {
    const nameQueryParameters =
      connectedAutocomplete?.useNewAPIFormat && parameters?.search
        ? [
            {
              name: 'name[lk]',
              value: parameters.search.conditions[0].values.$lk.slice(1, -1)
            }
          ]
        : [];

    return buildListingEndpoint({
      apiFormat: connectedAutocomplete?.useNewAPIFormat
        ? 'JSON-LD'
        : 'Standard',
      baseEndpoint: connectedAutocomplete?.endpoint,
      customQueryParameters: [
        ...(connectedAutocomplete?.customQueryParameters || []),
        ...nameQueryParameters
      ],
      parameters: {
        ...parameters,
        search: {
          conditions: [
            ...(connectedAutocomplete?.additionalConditionParameters || []),
            ...(parameters.search?.conditions || [])
          ],
          ...parameters.search
        },
        sort: { [filterKey]: 'ASC' }
      }
    });
  };

  const fieldNamePath = split('.', fieldName);

  const changeAutocomplete = useCallback(
    (_, value): void => {
      if (change) {
        change({
          setFieldTouched,
          setFieldValue,
          setTouched,
          setValues,
          value,
          values
        });

        return;
      }

      setFieldTouched(fieldName, true, false);
      setFieldValue(fieldName, value);
    },
    [fieldName, touched, additionalMemoProps]
  );

  const blur = (): void => setFieldTouched(fieldName, true);

  const isOptionEqualToValue = useCallback(
    (option, value): boolean => {
      return isEmpty(value)
        ? false
        : equals(option[filterKey], value[filterKey]);
    },
    [filterKey]
  );

  const value = path(fieldNamePath, values);

  const error = path(fieldNamePath, touched)
    ? path(fieldNamePath, errors)
    : undefined;

  const disabled = getDisabled?.(values) || false;
  const isRequired = required || getRequired?.(values) || false;

  const AutocompleteField = useMemo(
    () =>
      isMultiple
        ? MultiConnectedAutocompleteField
        : SingleConnectedAutocompleteField,
    [isMultiple]
  );

  const deleteItem = (_, option): void => {
    const newValue = reject(propEq(option.id, 'id'), value);

    setFieldTouched(fieldName, true, false);
    setFieldValue(fieldName, newValue);
  };

  const chipProps = connectedAutocomplete && {
    color: connectedAutocomplete?.chipColor || 'default',
    onDelete: deleteItem
  };

  return useMemoComponent({
    Component: (
      <AutocompleteField
        chipProps={chipProps}
        dataTestId={dataTestId}
        decoder={connectedAutocomplete?.decoder}
        disableClearable={false}
        disabled={disabled}
        disableSelectAll={connectedAutocomplete?.disableSelectAll}
        disableSortedOptions={disableSortedOptions}
        error={error}
        field={filterKey}
        getEndpoint={getEndpoint}
        getOptionLabel={connectedAutocomplete?.getOptionLabel}
        getRenderedOptionText={connectedAutocomplete?.getRenderedOptionText}
        initialPage={1}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(label)}
        limitTags={connectedAutocomplete?.limitTags}
        name={fieldName}
        onBlur={blur}
        onChange={changeAutocomplete}
        optionProperty={connectedAutocomplete?.optionProperty}
        required={isRequired}
        searchConditions={connectedAutocomplete?.additionalConditionParameters}
        value={value ?? null}
      />
    ),
    memoProps: [
      value,
      error,
      disabled,
      isRequired,
      additionalMemoProps,
      connectedAutocomplete
    ]
  });
};

export default ConnectedAutocomplete;
