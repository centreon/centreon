import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';

import { Checkbox as CheckboxComponent } from '../../Checkbox';
import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const Checkbox = ({
  checkbox,
  fieldName
}: InputPropsWithoutGroup): JSX.Element => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

  const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const newValue = {
      ...value,
      checked: event.target.checked,
      label: event.target.id
    };

    setFieldValue(fieldName, newValue);
  };

  return useMemoComponent({
    Component: (
      <CheckboxComponent
        Icon={value?.Icon}
        checked={value?.checked}
        label={value?.label}
        labelPlacement={checkbox?.labelPlacement || 'end'}
        onChange={handleChange}
      />
    ),
    memoProps: [value]
  });
};

export default Checkbox;
