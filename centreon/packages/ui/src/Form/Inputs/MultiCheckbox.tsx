import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { equals, path, split } from 'ramda';

import { MultiCheckbox as MultiCheckboxComponent } from '../../Checkbox';
import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const MultiCheckbox = ({
  change,
  checkbox,
  fieldName
}: InputPropsWithoutGroup): JSX.Element => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

  const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const label = event.target.id;

    const newValue = value?.map((item) => {
      if (equals(item.label, label)) {
        return { ...item, checked: event.target.checked };
      }

      return item;
    });

    if (change) {
      change({ setFieldValue, value: newValue });

      return;
    }
    setFieldValue(fieldName, newValue);
  };

  return useMemoComponent({
    Component: (
      <MultiCheckboxComponent
        labelPlacement={checkbox?.labelPlacement || 'end'}
        row={checkbox?.row || false}
        values={value}
        onChange={handleChange}
      />
    ),
    memoProps: [value]
  });
};

export default MultiCheckbox;
