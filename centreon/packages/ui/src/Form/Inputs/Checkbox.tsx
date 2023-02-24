import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';

import { Checkbox as CheckboxComponent } from '../../Checkbox';
import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const Checkbox = ({
  change,
  checkbox,
  fieldName
}: InputPropsWithoutGroup): JSX.Element => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

  const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const label = event.target.id;
    const newValue = { checked: event.target.checked, label };
    if (change) {
      change({ setFieldValue, value: newValue });

      return;
    }
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
