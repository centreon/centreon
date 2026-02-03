import { type FormikValues, useFormikContext } from "formik";
import { equals, isEmpty, path, split } from "ramda";
import { useCallback, useMemo } from "react";
import { useTranslation } from "react-i18next";

import {
  buildListingEndpoint,
  SingleConnectedAutocompleteField,
  useMemoComponent,
} from "../..";
import MultiConnectedAutocompleteField from "../../InputField/Select/Autocomplete/Connected/Multi";

import { type InputPropsWithoutGroup, InputType } from "./models";

const defaultFilterKey = "name";

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
  disableSortedOptions = false,
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const { values, touched, errors, setFieldValue, setFieldTouched } =
    useFormikContext<FormikValues>();

  const filterKey = connectedAutocomplete?.filterKey || defaultFilterKey;

  const isMultiple = equals(type, InputType.MultiConnectedAutocomplete);

  const getEndpoint = (parameters): string =>
    buildListingEndpoint({
      baseEndpoint: connectedAutocomplete?.endpoint,
      parameters: {
        ...parameters,
        search: {
          conditions: [
            ...(connectedAutocomplete?.additionalConditionParameters || []),
            ...(parameters.search?.conditions || []),
          ],
          ...parameters.search,
        },
        sort: { [filterKey]: "ASC" },
      },
    });

  const fieldNamePath = split(".", fieldName);

  const changeAutocomplete = useCallback(
    (_, value): void => {
      if (change) {
        change({ setFieldValue, value });

        return;
      }

      setFieldValue(fieldName, value);

      if (path(fieldNamePath, touched)) {
        return;
      }

      setFieldTouched(fieldName, true);
    },
    [fieldName, touched, additionalMemoProps],
  );

  const blur = (): void => setFieldTouched(fieldName, true);

  const isOptionEqualToValue = useCallback(
    (option, value): boolean => {
      return isEmpty(value)
        ? false
        : equals(option[filterKey], value[filterKey]);
    },
    [filterKey],
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
    [isMultiple],
  );

  return useMemoComponent({
    Component: (
      <AutocompleteField
        dataTestId={dataTestId}
        disableClearable={false}
        disableSortedOptions={disableSortedOptions}
        disabled={disabled}
        error={error}
        field={filterKey}
        getEndpoint={getEndpoint}
        getOptionLabel={connectedAutocomplete?.getOptionLabel}
        getRenderedOptionText={connectedAutocomplete?.getRenderedOptionText}
        initialPage={1}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(label)}
        name={fieldName}
        optionProperty={connectedAutocomplete?.optionProperty}
        required={isRequired}
        value={value ?? null}
        onBlur={blur}
        onChange={changeAutocomplete}
      />
    ),
    memoProps: [
      value,
      error,
      disabled,
      isRequired,
      additionalMemoProps,
      connectedAutocomplete,
    ],
  });
};

export default ConnectedAutocomplete;
