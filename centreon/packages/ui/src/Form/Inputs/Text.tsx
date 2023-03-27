import { ChangeEvent, useCallback, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext, FormikValues } from 'formik';
import { equals, isEmpty, not, path, split } from 'ramda';

import { TextField, useMemoComponent } from '../..';

import PasswordEndAdornment from './PasswordEndAdornment';
import { InputPropsWithoutGroup, InputType } from './models';

const Text = ({
  dataTestId,
  label,
  fieldName,
  type,
  required,
  getDisabled,
  getRequired,
  change,
  additionalMemoProps,
  text
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const [isVisible, setIsVisible] = useState(false);

  const { values, setFieldValue, touched, errors, handleBlur } =
    useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const changeText = (event: ChangeEvent<HTMLInputElement>): void => {
    const { value } = event.target;
    if (change) {
      change({ setFieldValue, value });

      return;
    }

    const formattedValue =
      equals(text?.type, 'number') && !isEmpty(value)
        ? parseInt(value, 10)
        : value;

    setFieldValue(fieldName, formattedValue);
  };

  const changeVisibility = (): void => {
    setIsVisible((currentIsVisible) => !currentIsVisible);
  };

  const value = path(fieldNamePath, values);

  const error = path(fieldNamePath, touched)
    ? path(fieldNamePath, errors)
    : undefined;

  const passwordEndAdornment = useCallback(
    (): JSX.Element | null =>
      equals(type, InputType.Password) ? (
        <PasswordEndAdornment
          changeVisibility={changeVisibility}
          isVisible={isVisible}
        />
      ) : null,
    [isVisible]
  );

  const getInputType = (): string => {
    if (text?.type) {
      return text.type;
    }

    return equals(type, InputType.Password) && not(isVisible)
      ? 'password'
      : 'text';
  };

  const disabled = getDisabled?.(values) || false;
  const isRequired = required || getRequired?.(values) || false;

  return useMemoComponent({
    Component: (
      <TextField
        fullWidth
        EndAdornment={passwordEndAdornment}
        ariaLabel={t(label) || ''}
        dataTestId={dataTestId || ''}
        disabled={disabled}
        error={error as string | undefined}
        label={t(label)}
        required={isRequired}
        type={getInputType()}
        value={value || ''}
        onBlur={handleBlur(fieldName)}
        onChange={changeText}
      />
    ),
    memoProps: [
      error,
      value,
      isVisible,
      disabled,
      isRequired,
      additionalMemoProps
    ]
  });
};

export default Text;
