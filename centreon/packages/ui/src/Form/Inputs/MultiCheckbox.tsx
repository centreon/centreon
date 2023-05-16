import { ChangeEvent, useEffect } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { equals, includes, or, path, split } from 'ramda';

import { Box, Typography } from '@mui/material';

import { MultiCheckbox as MultiCheckboxComponent } from '../../Checkbox';
import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const MultiCheckbox = ({
  checkbox,
  fieldName,
  additionalLabel,
  getDisabled,
  hideInput
}: InputPropsWithoutGroup): JSX.Element => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

  const disabled = getDisabled?.(values) || false;
  const hideCheckbox = hideInput?.(values) || false;

  useEffect(() => {
    if (or(disabled, hideCheckbox)) {
      const resetedValue = value?.map((elm) => ({ ...elm, checked: false }));
      setFieldValue(fieldName, resetedValue);
    }
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
        <MultiCheckboxComponent
          disabled={disabled}
          labelPlacement={checkbox?.labelPlacement || 'end'}
          options={checkbox?.options}
          row={checkbox?.row || false}
          values={value}
          onChange={handleChange}
        />
      </Box>
    ),
    memoProps: [value, disabled, hideCheckbox]
  });
};

export default MultiCheckbox;
