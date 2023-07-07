import { ReactElement, useCallback } from 'react';

import { useField } from 'formik';

import { RoleInputSelect } from '../common/RoleInputSelect';

type RoleFieldProps = {
  id: string;
  label?: string;
  name: string;
};

const RoleInputField = ({ label, ...props }: RoleFieldProps): ReactElement => {
  const [field, meta] = useField(props);

  const onInputChange = useCallback((e) => field.onChange(e), [field]);

  return (
    <RoleInputSelect
      initialValue={meta.initialValue}
      {...field}
      {...props}
      {...(label && { label })}
      onChange={onInputChange}
    />
  );
};

export { RoleInputField };
