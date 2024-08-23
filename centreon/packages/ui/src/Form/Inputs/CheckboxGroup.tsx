import { ChangeEvent, useEffect } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { path, equals, includes, split } from 'ramda';

import { Box, Typography } from '@mui/material';

import { useMemoComponent } from '../..';
import { CheckboxGroup as CheckboxGroupComponent } from '../../Checkbox';

import { InputPropsWithoutGroup } from './models';

const CheckboxGroup = ({
  checkbox,
  fieldName,
  additionalLabel,
  getDisabled,
  hideInput,
  dataTestId
}: InputPropsWithoutGroup): JSX.Element => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

  const disabled = getDisabled?.(values) || false;
  const hideCheckbox = hideInput?.(values) || false;

  useEffect(() => {
    if (!disabled && !hideCheckbox) {
      return;
    }
    const resetedValue = value?.map((element) => ({
      ...element,
      checked: false
    }));
    setFieldValue(fieldName, resetedValue);
  }, [disabled, hideCheckbox]);

  const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const label = event.target.id;
    if (!includes(label, value)) {
      setFieldValue(fieldName, [...value, label]);

      return;
    }

    setFieldValue(
      fieldName,
      value?.filter((elm) => !equals(elm, label))
    );
  };

  return useMemoComponent({
    Component: hideCheckbox ? (
      <Box />
    ) : (
      <Box>
        {additionalLabel && <Typography>{additionalLabel}</Typography>}
        <CheckboxGroupComponent
          dataTestId={dataTestId || ''}
          direction={checkbox?.direction}
          disabled={disabled}
          labelPlacement={checkbox?.labelPlacement || 'end'}
          options={checkbox?.options as Array<string>}
          values={value}
          onChange={handleChange}
        />
      </Box>
    ),
    memoProps: [value, disabled, hideCheckbox]
  });
};

export default CheckboxGroup;
