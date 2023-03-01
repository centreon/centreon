import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { equals, includes, path, split } from 'ramda';

import { MultiCheckbox as MultiCheckboxComponent } from '../../Checkbox';
import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const MultiCheckbox = ({
  checkbox,
  fieldName
}: InputPropsWithoutGroup): JSX.Element => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

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
    Component: (
      <MultiCheckboxComponent
        initialValues={checkbox?.options}
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
