import { FormikHandlers, useFormik } from 'formik';
import { propEq, reject } from 'ramda';

import { DashboardAccessRights } from '../api/models';

interface UseShareFormProps {
  shares: Array<DashboardAccessRights>;
}

interface UseShareFormState extends Pick<FormikHandlers, 'handleChange'> {
  dirty: boolean;
  getInputName: (index: number) => string;
  removeContact: (id: number | string) => () => void;
  values: Array<DashboardAccessRights>;
}

const useShareForm = ({ shares }: UseShareFormProps): UseShareFormState => {
  const { handleChange, values, setValues, dirty } = useFormik<
    Array<DashboardAccessRights>
  >({
    initialValues: shares,
    onSubmit: () => undefined
  });

  const getInputName = (index: number): string => `${index}.role`;

  const removeContact = (id: number | string) => (): void => {
    setValues((currentValues) => reject(propEq('id', id), currentValues));
  };

  return {
    dirty,
    getInputName,
    handleChange,
    removeContact,
    values
  };
};

export default useShareForm;
