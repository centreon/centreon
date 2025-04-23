import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';

import { Box } from '@mui/material';

import { useMemoComponent } from '../..';
import { Checkbox as CheckboxComponent } from '../../Checkbox';

import { InputPropsWithoutGroup } from './models';

const Checkbox = ({
  checkbox,
  fieldName,
  getDisabled,
  hideInput,
  dataTestId
}: InputPropsWithoutGroup): JSX.Element => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

  const disabled = getDisabled?.(values) || false;
  const hideCheckbox = hideInput?.(values) || false;

  const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const newValue = {
      ...value,
      checked: event.target.checked,
      label: event.target.id
    };

    setFieldValue(fieldName, newValue);
  };

  return useMemoComponent({
    Component: hideCheckbox ? (
      <Box />
    ) : (
      <CheckboxComponent
        Icon={value?.Icon}
        checked={value?.checked}
        dataTestId={dataTestId || ''}
        disabled={disabled}
        label={value?.label}
        labelPlacement={checkbox?.labelPlacement || 'end'}
        onChange={handleChange}
      />
    ),
    memoProps: [value, disabled, hideCheckbox]
  });
};

export default Checkbox;
